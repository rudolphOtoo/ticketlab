<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\MailCategory;
use App\Enums\OrderStatus;
use App\Mail\TicketIssuedMail;
use App\Models\Order;
use App\Services\Payment\TicketPassPdf;
use App\Support\Scheduler\SchedulerBudget;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

use function Illuminate\Support\defer;

/**
 * The buyer's ticket email (PDF passes attached). The ticket page and its PDF download are the main way to get a
 * ticket; this email is the backup copy.
 *
 * Sent right after payment is confirmed (webhook, browser return or reconcile), once the response has gone out, so
 * nobody waits for the PDF or the mail server. Each order records whether its email is still owed
 * (ticket_email_due_at), so the cron only retries what is left: emails that failed, that a killed request never
 * finished (the claim below expires), or that waited for the next day's quota.
 *
 * An attempt first claims the order with one conditional UPDATE, so the immediate send and a cron retry can never
 * both send it.
 */
final class TicketEmailer
{
    /** A claimed attempt that never finished (request killed) may be retried after this. */
    public const int CLAIM_MINUTES = 3;

    /** Attempts before giving up (the ticket page still works; the buyer or an admin can resend). */
    public const int MAX_ATTEMPTS = 5;

    /** Longest one attempt may take: rendering the PDF plus the SMTP timeout. */
    private const int WORST_CASE_SECONDS = 15;

    /** Minutes to wait after the 1st, 2nd, 3rd and 4th failed attempt. */
    private const array BACKOFF_MINUTES = [2, 10, 30, 120];

    public function __construct(
        private readonly MailQuota $quota,
        private readonly TicketPassPdf $passes,
    ) {}

    /**
     * Marks the order's email as owed and sends it once the current response (or command) has finished.
     */
    public function sendSoon(Order|int $order): void
    {
        $orderId = $order instanceof Order ? (int) $order->getKey() : $order;

        Order::query()
            ->whereKey($orderId)
            ->toBase()
            ->update([
                'ticket_email_due_at' => now(),
                'ticket_email_attempts' => 0,
                'ticket_email_error' => null,
            ]);

        defer(
            function () use ($orderId): void {
                // Inside a scheduler run (reconcile confirmed the payment) only if one send still fits; otherwise the
                // owed email goes out on the next run.
                if (SchedulerBudget::allows(self::WORST_CASE_SECONDS)) {
                    $this->attempt($orderId);
                }
            },
            'ticket-email-' . $orderId,
        )->always();
    }

    /**
     * One attempt, if the order's email is owed and due now.
     *
     * @return 'sent'|'deferred'|'failed'|'gave_up'|'skipped'
     */
    public function attempt(int $orderId): string
    {
        if (!$this->claim($orderId)) {
            return 'skipped';
        }

        $order = Order::query()
            ->with(['attendees.ticketType.event', 'attendees.subdivision', 'organization'])
            ->find($orderId);

        if (
            $order === null
            || $order->payment_status !== OrderStatus::Paid
            || $order->attendees->isEmpty()
            || !filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)
        ) {
            $this->finish($orderId, ['ticket_email_error' => 'No paid passes or no valid email address.']);

            return 'skipped';
        }

        if (!$this->quota->reserve(MailCategory::Ticket)) {
            $this->quota->recordDeferral();
            $this->update($orderId, [
                'ticket_email_due_at' => CarbonImmutable::now()->addSeconds($this->quota->secondsUntilReset(1800)),
                'ticket_email_attempts' => DB::raw('ticket_email_attempts - 1'),
                'ticket_email_error' => 'Daily email limit reached; sending after midnight.',
            ]);

            return 'deferred';
        }

        try {
            $pdf = $this->passes->render($order);
            MailQuota::counted(static fn() => Mail::to($order->customer_email, $order->customer_name)->send(
                new TicketIssuedMail($order, $pdf->output()),
            ));
        } catch (Throwable $exception) {
            $this->quota->release(MailCategory::Ticket);

            return $this->failed($order, $exception);
        }

        $this->finish($orderId, ['ticket_emailed_at' => now(), 'ticket_email_error' => null]);

        return 'sent';
    }

    /**
     * Retries owed emails, oldest first, until $max are done or $deadline passes.
     *
     * @return array<string, int> outcome => count
     */
    public function retryDue(int $max, CarbonImmutable $deadline): array
    {
        $outcomes = [];

        $ids = Order::query()
            ->whereNotNull('ticket_email_due_at')
            ->where('ticket_email_due_at', '<=', now())
            ->orderBy('ticket_email_due_at')
            ->limit(max(1, $max))
            ->pluck('id');

        foreach ($ids as $id) {
            if (CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
                break;
            }

            $outcome = $this->attempt((int) $id);
            $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;
        }

        return $outcomes;
    }

    /** Whether any email is owed and due now: one indexed query, so an idle cron run stops here. */
    public function hasDue(): bool
    {
        return Order::query()->whereNotNull('ticket_email_due_at')->where('ticket_email_due_at', '<=', now())->exists();
    }

    /**
     * Counts for the platform dashboard.
     *
     * @return array{waiting: int, gave_up: int}
     */
    public function counts(): array
    {
        return [
            'waiting' => Order::query()->whereNotNull('ticket_email_due_at')->count(),
            'gave_up' => Order::query()
                ->whereNull('ticket_email_due_at')
                ->whereNull('ticket_emailed_at')
                ->where('ticket_email_attempts', '>=', self::MAX_ATTEMPTS)
                ->count(),
        ];
    }

    private function claim(int $orderId): bool
    {
        return Order::query()
            ->whereKey($orderId)
            ->whereNotNull('ticket_email_due_at')
            ->where('ticket_email_due_at', '<=', now())
            ->toBase()
            ->update([
                'ticket_email_due_at' => now()->addMinutes(self::CLAIM_MINUTES),
                'ticket_email_attempts' => DB::raw('ticket_email_attempts + 1'),
            ]) === 1;
    }

    /**
     * @return 'failed'|'gave_up'
     */
    private function failed(Order $order, Throwable $exception): string
    {
        $attempts = (int) Order::query()->whereKey($order->getKey())->value('ticket_email_attempts');
        $givingUp = $attempts >= self::MAX_ATTEMPTS;
        $wait = self::BACKOFF_MINUTES[min($attempts, count(self::BACKOFF_MINUTES)) - 1] ?? 120;

        $this->update((int) $order->getKey(), [
            'ticket_email_due_at' => $givingUp ? null : now()->addMinutes($wait),
            'ticket_email_error' => Str::limit($exception->getMessage(), 480),
        ]);

        Log::warning($givingUp ? 'Gave up on a ticket email.' : 'A ticket email failed; it will be retried.', [
            'order_reference' => $order->reference,
            'attempt' => $attempts,
            'error' => $exception->getMessage(),
        ]);

        return $givingUp ? 'gave_up' : 'failed';
    }

    /**
     * @param array<string, mixed> $values
     */
    private function finish(int $orderId, array $values): void
    {
        $this->update($orderId, ['ticket_email_due_at' => null, ...$values]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function update(int $orderId, array $values): void
    {
        Order::query()->whereKey($orderId)->toBase()->update($values);
    }
}
