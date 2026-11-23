<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Mail\BroadcastSchedule;
use App\Services\Mail\BroadcastSender;
use App\Support\ActiveEvent;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An event's life after it has sold anything: it is archived (hidden, kept) or cancelled (it won't take place) rather
 * than deleted, because its passes, orders and payments are financial records. Only an event that never took an
 * order, a seat hold or a pass can be deleted. Every change is written to the organisation's audit trail.
 */
final readonly class EventLifecycle
{
    /** Walk-in cash sales are stored with a made-up address on this domain. */
    private const string WALK_IN_EMAIL_DOMAIN = '@cashdesk.local';

    public const string HAS_PASSES = "This event has sold passes, so it can't be deleted. Archive it to hide it, or cancel it if it won't take place.";

    public const string HAS_ORDERS = "This event has orders or seat holds, so it can't be deleted. Archive it to hide it, or cancel it if it won't take place.";

    public function __construct(
        private BroadcastSender $broadcasts,
    ) {}

    /**
     * Why the event can't be deleted, or null when nothing was ever sold or held for it.
     */
    public function deleteBlocker(Event $event): ?string
    {
        $tiers = TicketType::query()->select('id')->where('event_id', $event->id);

        if (Attendee::query()->whereIn('ticket_type_id', $tiers)->exists()) {
            return self::HAS_PASSES;
        }

        // Every online order starts from a seat hold (kept after it expires or is paid), and a cash sale issues passes.
        return Reservation::query()->whereIn('ticket_type_id', $tiers)->exists() ? self::HAS_ORDERS : null;
    }

    /**
     * Leaves the public pages and stops selling. Passes, ticket pages, PDFs and reports keep working.
     */
    public function archive(Event $event, User $actor): void
    {
        if ($event->isArchived()) {
            return;
        }

        $event->forceFill(['archived_at' => now()])->save();
        $this->audit($event, $actor, 'event.archived');
    }

    /**
     * Back exactly as it was before archiving (published or draft).
     */
    public function unarchive(Event $event, User $actor): void
    {
        if (!$event->isArchived()) {
            return;
        }

        $event->forceFill(['archived_at' => null])->save();
        $this->audit($event, $actor, 'event.unarchived');
    }

    /**
     * Stops sales at once, shows the message on the public page and refuses the passes at the gate. Orders, passes and
     * payments are kept; refunds are made by hand from refundableOrders().
     */
    public function cancel(Event $event, User $actor, string $message): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $event->forceFill(['cancelled_at' => now(), 'cancellation_message' => $message])->save();
        $this->audit($event, $actor, 'event.cancelled', ['message' => $message]);
    }

    /**
     * Emails the cancellation message to every buyer, spread over the following days when it is more than today's
     * share of the daily email cap. Null when there is nobody to email.
     */
    public function emailBuyersAboutCancellation(Event $event, User $actor): ?BroadcastSchedule
    {
        $buyers = $this->buyers($event);

        if (!$event->isCancelled() || $buyers->isEmpty()) {
            return null;
        }

        $when = $event->start_at->format('l, F j, Y');
        $broadcast = Broadcast::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'user_id' => $actor->id,
            'subject' => "Cancelled: {$event->title}",
            'body' => "{$event->title} on {$when} has been cancelled.\n\n{$event->cancellation_message}",
            'audience' => 'all',
            'recipients_count' => $buyers->count(),
        ]);

        $this->audit($event, $actor, 'event.cancellation_emailed', ['buyers' => $buyers->count()]);

        return $this->broadcasts->queue($broadcast, $buyers);
    }

    /**
     * One row per buyer of a paid pass, by email; walk-in cash sales have no real address and are left out.
     *
     * @return Collection<int, array{email: string, name: string}>
     */
    public function buyers(Event $event): Collection
    {
        return $this
            ->paidOrders($event)
            ->where('customer_email', 'not like', '%' . self::WALK_IN_EMAIL_DOMAIN)
            ->orderBy('id')
            ->get(['customer_email', 'customer_name'])
            ->unique(static fn(Order $order): string => Str::lower($order->customer_email))
            ->map(static fn(Order $order): array => [
                'email' => $order->customer_email,
                'name' => $order->customer_name,
            ])
            ->values();
    }

    /**
     * Paid orders with at least one pass for this event, grouped by the zone that was paid, for manual refunds.
     *
     * @return list<array{zone: string, orders: list<Order>, total: float}>
     */
    public function refundableOrders(Event $event): array
    {
        $orders = $this
            ->paidOrders($event)
            ->with('subdivision:id,name')
            ->withCount([
                'attendees as passes_count' =>
                    static fn(Builder $passes) => $passes->whereIn('ticket_type_id', TicketType::query()
                        ->select('id')
                        ->where('event_id', $event->id)),
            ])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        // Keyed by zone name for sorting; the name is also kept in the value, since PHP turns a numeric key into an int.
        /** @var array<string, array{zone: string, orders: list<Order>, total: float}> $zones */
        $zones = [];

        foreach ($orders as $order) {
            $name = $order->subdivision->name ?? 'No zone';
            $zones[$name] ??= ['zone' => $name, 'orders' => [], 'total' => 0.0];
            $zones[$name]['orders'][] = $order;
            $zones[$name]['total'] = round($zones[$name]['total'] + (float) $order->gross_amount, 2);
        }

        ksort($zones, SORT_STRING);

        return array_values($zones);
    }

    /**
     * Deletes an event that never sold or held anything, with its tiers and programme. Returns the reason instead when
     * it can't be deleted.
     */
    public function delete(Event $event, User $actor): ?string
    {
        return DB::transaction(function () use ($event, $actor): ?string {
            // Locked, so a checkout can't hold seats between the check and the delete.
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            if ($locked === null) {
                return null;
            }

            TicketType::query()->where('event_id', $locked->id)->lockForUpdate()->get(['id']);
            $blocker = $this->deleteBlocker($locked);

            if ($blocker !== null) {
                return $blocker;
            }

            $wasActive = ActiveEvent::id($locked->organization_id) === $locked->id;
            $this->audit($locked, $actor, 'event.deleted', ['slug' => $locked->slug]);
            $locked->delete();

            // The next resolve falls back to (and pins) another event instead of the one just deleted.
            if ($wasActive) {
                ActiveEvent::forget();
            }

            DB::afterCommit(static fn() => MediaUrl::deleteStored($locked->banner_path));

            return null;
        });
    }

    /**
     * @return Builder<Order>
     */
    private function paidOrders(Event $event): Builder
    {
        $tiers = TicketType::query()->select('id')->where('event_id', $event->id);

        return Order::query()
            ->where('organization_id', $event->organization_id)
            ->where('payment_status', OrderStatus::Paid->value)
            ->whereHas('attendees', static fn(Builder $passes) => $passes->whereIn('ticket_type_id', $tiers));
    }

    /**
     * @param array<string, mixed> $details
     */
    private function audit(Event $event, User $actor, string $action, array $details = []): void
    {
        AuditLog::query()->create([
            'organization_id' => $event->organization_id,
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => 'event',
            'subject_id' => $event->id,
            'details' => ['title' => $event->title, ...$details],
        ]);
    }
}
