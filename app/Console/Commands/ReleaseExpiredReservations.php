<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Scheduler\Tasks\ReleaseExpiredHolds;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Manual run of the scheduler's release_holds task (one batch). */
class ReleaseExpiredReservations extends Command
{
    protected $signature = 'reservations:release-expired';

    protected $description = 'Release expired ticket reservation holds back into available inventory';

    public function handle(ReleaseExpiredHolds $task): int
    {
        $result = $task->run(CarbonImmutable::now()->addSeconds((int) config(
            'scheduler.release_holds.time_budget_seconds',
        )));

        $this->info('Released ' . ($result->counts['released'] ?? 0) . ' expired reservation(s).');

        return $result->errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
