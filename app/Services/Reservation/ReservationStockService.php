<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\TicketType;

class ReservationStockService
{
    /**
     * @param array{ticket: TicketType, subdivision_id: int|null} $context
     */
    public function reuse(Reservation $reservation, array $context, int $quantity, int $available): Reservation
    {
        $ticket = $context['ticket'];
        $delta = $quantity - (int) $reservation->quantity;

        if ($delta > 0) {
            if ($available < $delta) {
                throw new TicketSoldOutException('Requested quantity is no longer available.');
            }

            $ticket->decrement('available_quantity', $delta);
        } elseif ($delta < 0) {
            $restore = min(abs($delta), max(0, (int) $ticket->total_capacity - $available));

            if ($restore > 0) {
                $ticket->increment('available_quantity', $restore);
            }
        }

        $currentSubdivisionId = $reservation->subdivision_id !== null ? (int) $reservation->subdivision_id : null;

        if ($delta !== 0 || $currentSubdivisionId !== $context['subdivision_id']) {
            $reservation->update([
                'quantity' => $quantity,
                'subdivision_id' => $context['subdivision_id'],
            ]);
        }

        return $reservation->refresh();
    }

    /**
     * @param array{
     *     ticket: TicketType,
     *     organization_id: int,
     *     session_id: string,
     *     subdivision_id: int|null
     * } $context
     */
    public function create(array $context, int $quantity, int $available): Reservation
    {
        $ticket = $context['ticket'];

        if ($available < $quantity) {
            throw new TicketSoldOutException('Requested quantity is no longer available.');
        }

        $ticket->decrement('available_quantity', $quantity);

        return Reservation::query()->create([
            'organization_id' => $context['organization_id'],
            'ticket_type_id' => $ticket->getKey(),
            'subdivision_id' => $context['subdivision_id'],
            'session_id' => $context['session_id'],
            'quantity' => $quantity,
            'expires_at' => now()->addMinutes(TicketReservationService::HOLD_MINUTES),
            'status' => ReservationStatus::Active,
        ]);
    }
}
