<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Subdivision;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketReservationService
{
    public const HOLD_MINUTES = 15;

    public function __construct(
        private readonly ReservationReuseService $reuseService,
    ) {}

    /**
     * Atomically reserve ticket stock for a public checkout session.
     */
    public function hold(TicketType $ticketType, int $quantity, string $sessionId, ?int $subdivisionId): Reservation
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Reservation quantity must be at least one.');
        }

        return DB::transaction(function () use ($ticketType, $quantity, $sessionId, $subdivisionId): Reservation {
            /** @var TicketType $ticket */
            $ticket = TicketType::query()->whereKey($ticketType->getKey())->lockForUpdate()->firstOrFail();
            $organizationId = $this->organizationId($ticket);

            $this->assertValidSubdivision($subdivisionId, $organizationId);

            if ($ticket->available_quantity < $quantity) {
                throw new TicketSoldOutException('Requested quantity is no longer available.');
            }

            $ticket->decrement('available_quantity', $quantity);

            return Reservation::query()->create([
                'organization_id' => $organizationId,
                'ticket_type_id' => $ticket->getKey(),
                'subdivision_id' => $subdivisionId,
                'session_id' => $sessionId,
                'quantity' => $quantity,
                'expires_at' => now()->addMinutes(self::HOLD_MINUTES),
                'status' => ReservationStatus::Active,
            ]);
        });
    }

    /**
     * Reuse an unexpired hold for the same session and ticket tier, adjusting
     * stock only when the requested quantity changes.
     */
    public function holdOrReuse(
        TicketType $ticketType,
        int $quantity,
        string $sessionId,
        ?int $subdivisionId,
    ): Reservation {
        return $this->reuseService->holdOrReuse($ticketType, $quantity, $sessionId, $subdivisionId);
    }

    private function organizationId(TicketType $ticket): int
    {
        return (int) Event::query()->whereKey($ticket->event_id)->value('organization_id');
    }

    private function assertValidSubdivision(?int $subdivisionId, int $organizationId): void
    {
        if (
            $subdivisionId !== null
            && !Subdivision::query()->whereKey($subdivisionId)->where('organization_id', $organizationId)->exists()
        ) {
            throw new InvalidArgumentException('The selected subdivision does not belong to this organization.');
        }
    }
}
