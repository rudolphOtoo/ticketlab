<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Event;
use App\Models\Subdivision;
use App\Models\TicketType;

/**
 * Whether an event may be published: every zone it sells in must be able to receive its own ticket money.
 */
final readonly class PayoutReadiness
{
    public function __construct(
        private PaystackSubaccountResolver $subaccounts,
    ) {}

    /**
     * Why the event cannot be published yet, naming the zones to fix; null when it is ready (or sells only free tickets).
     *
     * $addsPaidTier covers a paid ticket type that is about to be created or opened on an already published event.
     */
    public function blockingReason(Event $event, bool $addsPaidTier = false): ?string
    {
        $sellsPaidTickets =
            $addsPaidTier
            || TicketType::query()
                ->where('event_id', $event->id)
                ->where('is_active', true)
                ->where('price', '>', 0)
                ->exists();

        if (!$sellsPaidTickets) {
            return null;
        }

        $label = trim($event->organization->subdivision_label) !== ''
            ? $event->organization->subdivision_label
            : 'Zone';
        $zones = Subdivision::query()->where('organization_id', $event->organization_id)->orderBy('name')->get();

        if ($zones->isEmpty()) {
            return "Add at least one {$label} with a Paystack payout account before publishing an event with paid tickets.";
        }

        // forSubdivision() verifies each code with Paystack (cached), so an unknown or rejected code also blocks.
        $missing = $zones
            ->filter(fn(Subdivision $zone): bool => $this->subaccounts->forSubdivision($zone) === null)
            ->pluck('name');

        if ($missing->isEmpty()) {
            return null;
        }

        return (
            'Cannot publish yet: '
            . $missing->implode(', ')
            . ($missing->count() === 1 ? ' has' : ' have')
            . " no verified Paystack payout account. Set one under {$label} Payouts."
        );
    }
}
