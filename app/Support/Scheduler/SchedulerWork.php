<?php

declare(strict_types=1);

namespace App\Support\Scheduler;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Subdivision;
use App\Services\Mail\TicketEmailer;
use Illuminate\Support\Facades\DB;

/**
 * What the scheduler has to do right now, found with a handful of indexed EXISTS queries. When nothing is due the
 * endpoint answers at once and never starts `schedule:run` (which starts a process per task).
 */
final class SchedulerWork
{
    /**
     * @return list<string> the kinds of work due now; empty when the run can stop here
     */
    public static function due(): array
    {
        $checks = [
            'ticket-emails' => static fn(): bool => app(TicketEmailer::class)->hasDue(),
            'expired-holds' => static fn(): bool => Reservation::query()
                ->where('status', ReservationStatus::Active)
                ->where('expires_at', '<=', now())
                ->exists(),
            'pending-payments' => static fn(): bool => Order::query()
                ->where('payment_status', OrderStatus::Pending->value)
                ->where('created_at', '<=', now()->subMinutes((int) config('scheduler.reconcile.older_than_minutes')))
                ->exists(),
            'payout-checks' => static fn(): bool => Subdivision::query()
                ->whereNotNull('paystack_subaccount_code')
                ->where(static fn($query) => $query->whereNull('payout_checked_at')->orWhere(
                    'payout_checked_at',
                    '<',
                    now()->subHours((int) config('scheduler.payout_recheck.interval_hours')),
                ))
                ->exists(),
            'queued-jobs' => self::queuedJobsAvailable(...),
        ];

        return array_keys(array_filter($checks, static fn(callable $check): bool => $check()));
    }

    /** Jobs the worker could take now (also ones whose worker was killed and whose reservation expired). */
    private static function queuedJobsAvailable(): bool
    {
        $connection = (string) config('queue.default');

        if (config("queue.connections.{$connection}.driver") !== 'database') {
            return $connection !== 'sync'; // Another backend: let the worker look.
        }

        $table = (string) config("queue.connections.{$connection}.table", 'jobs');

        $retryAfter = (int) config("queue.connections.{$connection}.retry_after", 90);

        return DB::table($table)
            ->whereIn('queue', (array) config('queue.worker_queues'))
            ->where(static fn($query) => $query->where(static fn($available) => $available->whereNull(
                'reserved_at',
            )->where('available_at', '<=', now()->getTimestamp()))->orWhere(
                'reserved_at',
                '<=',
                now()->subSeconds($retryAfter)->getTimestamp(),
            ))
            ->exists();
    }
}
