<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentException;

class ReservationMatcher
{
    public function assertActive(?Reservation $reservation, Order $order, TicketType $ticketType): Reservation
    {
        if (
            $reservation === null
            || $reservation->status !== ReservationStatus::Active
            || $reservation->expires_at === null
            || $reservation->expires_at->isPast()
        ) {
            throw new OrderFulfillmentException(
                'The reservation is no longer active or has expired; fulfilment was blocked.',
            );
        }

        if (
            (int) $reservation->ticket_type_id !== (int) $ticketType->getKey()
            || (int) $reservation->organization_id !== (int) $order->organization_id
        ) {
            throw new OrderFulfillmentException('The reservation does not match this order.');
        }

        return $reservation;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function assertMetadataMatches(array $metadata, Reservation $reservation, TicketType $ticketType): void
    {
        $metadataQuantity = $metadata['quantity'] ?? null;
        $metadataTicketTypeId = $metadata['ticket_type_id'] ?? null;

        if ($metadataQuantity !== null && (int) $metadataQuantity !== (int) $reservation->quantity) {
            throw new OrderFulfillmentException('The order quantity does not match its reservation.');
        }

        if ($metadataTicketTypeId !== null && (int) $metadataTicketTypeId !== (int) $ticketType->getKey()) {
            throw new OrderFulfillmentException('The order ticket type does not match its reservation.');
        }
    }
}
