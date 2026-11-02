<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\TicketType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredReservations extends Command
{
    protected $signature = 'reservations:release-expired';

    protected $description = 'Release expired ticket reservation holds back into available inventory';

    public function handle(): int
    {
        $released = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Active)
            ->where('expires_at', '<=', now())
            ->select('id')
            ->chunkById(100, function ($reservations) use (&$released): void {
                foreach ($reservations as $reservation) {
                    if (!$this->release((int) $reservation->getKey())) {
                        continue;
                    }

                    $released++;
                }
            });

        $this->info("Released {$released} expired reservation(s).");

        return self::SUCCESS;
    }

    private function release(int $reservationId): bool
    {
        return DB::transaction(static function () use ($reservationId): bool {
            $reservationSnapshot = Reservation::query()->select(['id', 'ticket_type_id'])->find($reservationId);

            if ($reservationSnapshot === null) {
                return false;
            }

            $ticketType = TicketType::query()->whereKey($reservationSnapshot->ticket_type_id)->lockForUpdate()->first();

            $reservation = Reservation::query()->whereKey($reservationId)->lockForUpdate()->first();

            if (
                $reservation === null
                || $reservation->status !== ReservationStatus::Active
                || $reservation->expires_at->isFuture()
            ) {
                return false;
            }

            if ($ticketType !== null) {
                $restoredQuantity = min(
                    $ticketType->available_quantity + $reservation->quantity,
                    $ticketType->total_capacity,
                );

                $ticketType->update(['available_quantity' => $restoredQuantity]);
            }

            $reservation->update(['status' => ReservationStatus::Expired]);

            return true;
        });
    }
}
