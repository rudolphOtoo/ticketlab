<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentException;
use App\Services\Payment\PaystackGateway;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Settles orders stuck in "pending" because the browser closed and the webhook never arrived (or failed).
 *
 * Dry run by default. For each pending order older than --older-than minutes it asks Paystack's verify endpoint:
 *  - success: confirmed through exactly the webhook's path (same amount, currency and subaccount checks, same row
 *    lock, idempotent: an order the webhook already settled is left as it is);
 *  - failed / reversed / abandoned, or unknown to Paystack once its seat hold has lapsed: the order is closed and its
 *    seats are released;
 *  - anything else (still processing) or Paystack unreachable: left for the next run.
 */
class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile-pending
        {--older-than=10 : Only orders pending for at least this many minutes}
        {--execute : Actually confirm or release (otherwise a dry run that changes nothing)}';

    protected $description = 'Confirm or release orders stuck in pending by asking Paystack (dry run by default)';

    private const array RELEASE_STATUSES = [
        'failed' => OrderStatus::Failed,
        'reversed' => OrderStatus::Failed,
        'abandoned' => OrderStatus::Cancelled,
    ];

    private bool $execute = false;

    public function handle(PaystackGateway $paystack): int
    {
        // Also guards manual runs against the scheduled one; expires on its own if the request is killed.
        $store = Cache::store(config('scheduler.lock_store'))->getStore();

        if (!$store instanceof LockProvider) {
            $this->error('The scheduler lock store (SCHEDULER_LOCK_STORE) must support locks: use database or redis.');

            return self::FAILURE;
        }

        $lock = $store->lock('payments:reconcile-pending', 600);

        if (!$lock->get()) {
            $this->warn('Another reconciliation run is in progress; skipping this one.');

            return self::SUCCESS;
        }

        try {
            return $this->reconcileBatch($paystack);
        } finally {
            $lock->release();
        }
    }

    private function reconcileBatch(PaystackGateway $paystack): int
    {
        $minutes = max(1, (int) (is_numeric($this->option('older-than')) ? $this->option('older-than') : 10));
        $batch = max(1, (int) config('scheduler.reconcile.batch_size'));
        $deadline = now()->addSeconds(max(1, (int) config('scheduler.reconcile.time_budget_seconds')));
        $this->execute = (bool) $this->option('execute');
        $rows = [];

        // Oldest first and at most one batch per run, so a backlog drains over several runs inside the time limit.
        $orders = Order::query()
            ->where('payment_status', OrderStatus::Pending->value)
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($batch)
            ->get();

        foreach ($orders as $order) {
            if (now()->greaterThanOrEqualTo($deadline)) {
                $this->warn(
                    'Time budget reached; ' . ($orders->count() - count($rows)) . ' order(s) wait for the next run.',
                );

                break;
            }

            [$status, $action] = $this->reconcile($order, $paystack);
            $rows[] = [$order->reference, $order->created_at?->diffForHumans(), $status, $action];
        }

        $this->table(['Order', 'Created', 'Paystack', $this->execute ? 'Done' : 'Would do'], $rows);
        $this->info(
            $this->execute
                ? 'Reconciled ' . count($rows) . ' pending order(s).'
                : 'Dry run: nothing was changed. Re-run with --execute.',
        );

        return self::SUCCESS;
    }

    /**
     * @return array{string, string}
     */
    private function reconcile(Order $order, PaystackGateway $paystack): array
    {
        $reference = $order->gatewayReference();

        try {
            $data = $paystack->verify($reference);
            $status = is_string($data['status'] ?? null) ? $data['status'] : 'unknown';
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                return ['unreachable', 'skip (try next run)'];
            }

            // Paystack never saw a payment attempt. Only close it once its seat hold can no longer be paid for.
            return (
                $this->holdStillLive($order)
                    ? ['not found', 'skip (seat hold still live)']
                    : ['not found', $this->release($order, OrderStatus::Cancelled)]
            );
        } catch (Throwable $exception) {
            Log::warning('Pending order reconciliation could not reach Paystack.', [
                'order_reference' => $order->reference,
                'error' => $exception->getMessage(),
            ]);

            return ['unreachable', 'skip (try next run)'];
        }

        if ($status === 'success') {
            return [$status, $this->confirmPayment($order, $data, $paystack)];
        }

        if (array_key_exists($status, self::RELEASE_STATUSES)) {
            return [$status, $this->release($order, self::RELEASE_STATUSES[$status])];
        }

        return [$status, 'skip (still in progress)'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function confirmPayment(Order $order, array $data, PaystackGateway $paystack): string
    {
        if (!$this->execute) {
            return 'confirm and issue passes';
        }

        try {
            // The webhook's own path: same matching rules, same row lock; a no-op for an already settled order.
            $settled = $paystack->handleChargeSuccess($data);
        } catch (OrderFulfillmentException $exception) {
            Log::critical('Reconciled payment needs manual review.', [
                'order_reference' => $order->reference,
                'reason' => $exception->getMessage(),
            ]);

            return 'held for review: ' . $exception->getMessage();
        }

        Log::info('Pending order confirmed by reconciliation.', [
            'order_reference' => $order->reference,
            'organization_id' => $order->organization_id,
        ]);

        return $settled === null ? 'nothing to confirm' : 'confirmed (' . $settled->attendees->count() . ' pass(es))';
    }

    /**
     * Closes the order and returns its held seats. Locks the tier, then the reservation, then the order (the same
     * order as checkout and the expiry job), and does nothing if a webhook has settled the order in the meantime.
     */
    private function release(Order $order, OrderStatus $outcome): string
    {
        if (!$this->execute) {
            return 'close as ' . $outcome->value . ' and release seats';
        }

        return DB::transaction(static function () use ($order, $outcome): string {
            $reservation = $order->reservation_id === null
                ? null
                : Reservation::query()->select(['id', 'ticket_type_id'])->find($order->reservation_id);
            $tier = $reservation === null
                ? null
                : TicketType::query()->whereKey($reservation->ticket_type_id)->lockForUpdate()->first();
            $hold = $reservation === null
                ? null
                : Reservation::query()->whereKey($reservation->id)->lockForUpdate()->first();
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->payment_status !== OrderStatus::Pending) {
                return 'already settled elsewhere, left as is';
            }

            $locked->forceFill(['payment_status' => $outcome])->save();
            $seats = 0;

            if ($hold !== null && $hold->status === ReservationStatus::Active) {
                $hold->update(['status' => ReservationStatus::Released]);
                $tier?->restock((int) $hold->quantity);
                $seats = (int) $hold->quantity;
            }

            Log::info('Pending order closed by reconciliation.', [
                'order_reference' => $locked->reference,
                'status' => $outcome->value,
                'seats_released' => $seats,
            ]);

            return "closed as {$outcome->value}, {$seats} seat(s) released";
        });
    }

    private function holdStillLive(Order $order): bool
    {
        return (
            $order->reservation_id !== null
            && Reservation::query()
                ->whereKey($order->reservation_id)
                ->where('status', ReservationStatus::Active)
                ->where('expires_at', '>', now())
                ->exists()
        );
    }
}
