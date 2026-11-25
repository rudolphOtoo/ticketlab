<?php

declare(strict_types=1);

namespace App\Services\Scheduler\Tasks;

use App\Enums\ReservationStatus;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Services\Scheduler\SchedulerTask;
use App\Services\Scheduler\TaskResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Seats of holds older than 15 minutes go back on sale. Checkout also reclaims a tier's expired holds itself when
 * stock runs short, so a sell-out never waits for this. Each hold is re-checked under a row lock, so a re-run (or a
 * checkout racing it) never restocks twice.
 */
final class ReleaseExpiredHolds implements SchedulerTask
{
    public function name(): string
    {
        return 'release_holds';
    }

    public function isDue(): bool
    {
        return self::expired()->exists();
    }

    public function run(CarbonImmutable $deadline): TaskResult
    {
        $result = new TaskResult(['released' => 0]);

        $holds = self::expired()
            ->select(['id', 'organization_id'])
            ->orderBy('id')
            ->limit(max(1, (int) config('scheduler.release_holds.batch_size')))
            ->get();

        foreach ($holds as $hold) {
            if (CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
                break;
            }

            try {
                if (self::release((int) $hold->getKey())) {
                    $result->add('released');
                }
            } catch (Throwable $exception) {
                Log::error('Could not release an expired seat hold; it is retried on the next run.', [
                    'task' => $this->name(),
                    'organization_id' => $hold->organization_id,
                    'reservation_id' => $hold->getKey(),
                    'error' => $exception->getMessage(),
                ]);
                $result->error("reservation {$hold->getKey()}: {$exception->getMessage()}");
            }
        }

        return $result;
    }

    /**
     * Suspended and archived organisations are skipped; their holds are released once reactivated.
     *
     * @return Builder<Reservation>
     */
    private static function expired(): Builder
    {
        return Reservation::query()
            ->where('status', ReservationStatus::Active)
            ->where('expires_at', '<=', now())
            ->whereIn('organization_id', Organization::activeIds());
    }

    private static function release(int $reservationId): bool
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

            $reservation->update(['status' => ReservationStatus::Expired]);
            $ticketType?->restock((int) $reservation->quantity);

            return true;
        });
    }
}
