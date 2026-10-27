<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentException;

class ReservationFulfilmentService
{
    public function __construct(
        private readonly ReservationMatcher $matcher,
    ) {}

    /**
     * @return array{ticket_type: TicketType, reservation: Reservation, quantity: int}
     */
    public function fulfill(Order $order): array
    {
        $metadata = $order->metadata ?? [];
        $reservationId = $this->reservationId($order, $metadata);

        $ticketType = $this->lockTicketForReservation($reservationId);

        /** @var Reservation|null $reservation */
        $reservation = Reservation::query()->whereKey($reservationId)->lockForUpdate()->first();

        $reservation = $this->matcher->assertActive($reservation, $order, $ticketType);
        $this->matcher->assertMetadataMatches($metadata, $reservation, $ticketType);

        $updatedReservations = Reservation::query()
            ->whereKey($reservation->getKey())
            ->where('status', ReservationStatus::Active)
            ->update(['status' => ReservationStatus::Fulfilled]);

        if ($updatedReservations !== 1) {
            throw new OrderFulfillmentException('The reservation state changed during fulfilment.');
        }

        return [
            'ticket_type' => $ticketType,
            'reservation' => $reservation,
            'quantity' => max(1, (int) $reservation->quantity),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function reservationId(Order $order, array $metadata): int
    {
        $reservationId = $order->reservation_id ?? $metadata['reservation_id'] ?? null;

        if (!is_numeric($reservationId) || (int) $reservationId < 1) {
            throw new OrderFulfillmentException('Cannot fulfil an order without an associated reservation.');
        }

        return (int) $reservationId;
    }

    private function lockTicketForReservation(int $reservationId): TicketType
    {
        $ticketTypeId = Reservation::query()->whereKey($reservationId)->value('ticket_type_id');

        /** @var TicketType|null $ticketType */
        $ticketType = TicketType::query()->whereKey($ticketTypeId)->lockForUpdate()->first();

        if ($ticketType === null) {
            throw new OrderFulfillmentException('The ticket type attached to this order no longer exists.');
        }

        return $ticketType;
    }
}
