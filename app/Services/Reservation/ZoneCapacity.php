<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Attendee;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\TicketTypeZoneCapacity;

/**
 * Seats left for one ticket type in one zone.
 *
 * Computed from what is actually claimed (issued passes plus live, unexpired checkout holds), not from a second
 * running counter that could drift. Every caller that sells takes the ticket type's row lock first
 * (lockForUpdate), and all holds and sales for a ticket type go through that lock, so this read cannot oversell.
 */
final class ZoneCapacity
{
    /**
     * Remaining seats in the zone, or null when the zone has no cap of its own (only the tier total applies).
     */
    public function remaining(TicketType $ticketType, ?int $zoneId, ?int $excludeReservationId = null): ?int
    {
        if ($zoneId === null) {
            return null;
        }

        $capacity = TicketTypeZoneCapacity::query()
            ->where('ticket_type_id', $ticketType->id)
            ->where('subdivision_id', $zoneId)
            ->value('capacity');

        if ($capacity === null) {
            return null;
        }

        $issued = Attendee::query()
            ->where('ticket_type_id', $ticketType->id)
            ->where('subdivision_id', $zoneId)
            ->count();

        $held = (int) Reservation::query()
            ->where('ticket_type_id', $ticketType->id)
            ->where('subdivision_id', $zoneId)
            ->where('status', ReservationStatus::Active)
            ->where('expires_at', '>', now())
            ->when($excludeReservationId, static fn($query, int $id) => $query->whereKeyNot($id))
            ->sum('quantity');

        return max(0, (int) $capacity - $issued - $held);
    }

    /**
     * Seats the buyer can actually get: the tier's own stock, narrowed by the zone's remaining seats.
     */
    public function sellable(TicketType $ticketType, ?int $zoneId, ?int $excludeReservationId = null): int
    {
        $zoneRemaining = $this->remaining($ticketType, $zoneId, $excludeReservationId);
        $tierRemaining = max(0, (int) $ticketType->available_quantity);

        return $zoneRemaining === null ? $tierRemaining : min($tierRemaining, $zoneRemaining);
    }
}
