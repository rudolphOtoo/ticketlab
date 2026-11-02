<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Subdivision;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReservationReuseService
{
    public function __construct(
        private readonly ReservationStockService $stocks,
    ) {}

    public function holdOrReuse(
        TicketType $ticketType,
        int $quantity,
        string $sessionId,
        ?int $subdivisionId,
    ): Reservation {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Reservation quantity must be at least one.');
        }

        return DB::transaction(
            fn(): Reservation => $this->reuseOrCreate($ticketType, $quantity, $sessionId, $subdivisionId),
        );
    }

    private function reuseOrCreate(
        TicketType $ticketType,
        int $quantity,
        string $sessionId,
        ?int $subdivisionId,
    ): Reservation {
        $ticket = TicketType::query()->whereKey($ticketType->getKey())->lockForUpdate()->firstOrFail();
        $organizationId = $this->organizationId($ticket);

        $this->assertValidSubdivision($subdivisionId, $organizationId);

        $reservations = Reservation::query()
            ->where('session_id', $sessionId)
            ->where('ticket_type_id', $ticket->getKey())
            ->where('status', ReservationStatus::Active)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $released = $this->releaseExpired($reservations, $ticket);
        $reusable = $released['reservation'];
        $available = $released['available'];

        $context = [
            'ticket' => $ticket,
            'organization_id' => $organizationId,
            'session_id' => $sessionId,
            'subdivision_id' => $subdivisionId,
        ];

        if ($reusable !== null) {
            return $this->stocks->reuse($reusable, $context, $quantity, $available);
        }

        return $this->stocks->create($context, $quantity, $available);
    }

    /**
     * @param Collection<int, Reservation> $reservations
     * @return array{reservation: Reservation|null, available: int}
     */
    private function releaseExpired(Collection $reservations, TicketType $ticket): array
    {
        $reusable = null;
        $available = (int) $ticket->available_quantity;

        foreach ($reservations as $reservation) {
            if ($reservation->expires_at->isFuture()) {
                $reusable ??= $reservation;

                continue;
            }

            $restore = min((int) $reservation->quantity, max(0, (int) $ticket->total_capacity - $available));

            if ($restore > 0) {
                $ticket->increment('available_quantity', $restore);
                $available += $restore;
            }

            $reservation->update(['status' => ReservationStatus::Expired]);
        }

        return ['reservation' => $reusable, 'available' => $available];
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
