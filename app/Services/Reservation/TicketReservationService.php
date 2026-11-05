<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Models\Reservation;
use App\Models\TicketType;

class TicketReservationService
{
    public const HOLD_MINUTES = 15;

    public function __construct(
        private readonly ReservationReuseService $reuseService,
    ) {}

    /**
     * Atomically reserve ticket stock for a public checkout session, reusing an unexpired hold for the
     * same session and ticket tier and adjusting stock only when the requested quantity changes.
     */
    public function holdOrReuse(
        TicketType $ticketType,
        int $quantity,
        string $sessionId,
        ?int $subdivisionId,
    ): Reservation {
        return $this->reuseService->holdOrReuse($ticketType, $quantity, $sessionId, $subdivisionId);
    }
}
