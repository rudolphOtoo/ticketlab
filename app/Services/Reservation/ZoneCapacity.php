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

    /**
     * sellable() for every tier in every zone, in three grouped queries instead of three per pair.
     *
     * The public event page sends this grid to the browser so a zone switch updates "seats left" without a round
     * trip. Key 0 is "no zone chosen yet". Checkout re-checks the real number under the row lock.
     *
     * @param iterable<TicketType> $ticketTypes
     * @param list<int> $zoneIds
     *
     * @return array<int, array<int, int>> ticket type id => [zone id or 0 => seats the buyer can get]
     */
    public function sellableGrid(iterable $ticketTypes, array $zoneIds): array
    {
        $tiers = [];
        foreach ($ticketTypes as $ticketType) {
            $tiers[$ticketType->id] = max(0, (int) $ticketType->available_quantity);
        }

        if ($tiers === []) {
            return [];
        }

        $caps = $this->zoneRemainingByPair(array_keys($tiers), $zoneIds);
        $grid = [];

        foreach ($tiers as $tierId => $tierRemaining) {
            $grid[$tierId] = [0 => $tierRemaining];

            foreach ($zoneIds as $zoneId) {
                $zoneRemaining = $caps[$tierId][$zoneId] ?? null;
                $grid[$tierId][$zoneId] = $zoneRemaining === null
                    ? $tierRemaining
                    : min($tierRemaining, $zoneRemaining);
            }
        }

        return $grid;
    }

    /**
     * Remaining seats for each capped (tier, zone) pair; pairs without a zone cap are absent.
     *
     * @param list<int> $tierIds
     * @param list<int> $zoneIds
     *
     * @return array<int, array<int, int>>
     */
    private function zoneRemainingByPair(array $tierIds, array $zoneIds): array
    {
        if ($zoneIds === []) {
            return [];
        }

        $capacities = TicketTypeZoneCapacity::query()
            ->whereIn('ticket_type_id', $tierIds)
            ->whereIn('subdivision_id', $zoneIds)
            ->get(['ticket_type_id', 'subdivision_id', 'capacity']);

        if ($capacities->isEmpty()) {
            return [];
        }

        $issued = Attendee::query()
            ->whereIn('ticket_type_id', $tierIds)
            ->whereIn('subdivision_id', $zoneIds)
            ->groupBy('ticket_type_id', 'subdivision_id')
            ->selectRaw('ticket_type_id, subdivision_id, count(*) as claimed')
            ->get();

        $held = Reservation::query()
            ->whereIn('ticket_type_id', $tierIds)
            ->whereIn('subdivision_id', $zoneIds)
            ->where('status', ReservationStatus::Active)
            ->where('expires_at', '>', now())
            ->groupBy('ticket_type_id', 'subdivision_id')
            ->selectRaw('ticket_type_id, subdivision_id, sum(quantity) as claimed')
            ->get();

        $claimed = [];
        foreach ([$issued, $held] as $rows) {
            foreach ($rows as $row) {
                $key = (int) $row->getAttribute('ticket_type_id') . ':' . (int) $row->getAttribute('subdivision_id');
                $claimed[$key] = ($claimed[$key] ?? 0) + (int) $row->getAttribute('claimed');
            }
        }

        $remaining = [];
        foreach ($capacities as $capacity) {
            $key = $capacity->ticket_type_id . ':' . $capacity->subdivision_id;
            $remaining[$capacity->ticket_type_id][$capacity->subdivision_id] = max(
                0,
                (int) $capacity->capacity - ($claimed[$key] ?? 0),
            );
        }

        return $remaining;
    }
}
