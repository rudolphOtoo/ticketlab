<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Scheduler\SchedulerRunner;
use Illuminate\Console\Command;

/**
 * One scheduler run, exactly as /api/run-scheduler does it. For hosts with a real cron (`schedule:run` every minute
 * runs this) and for running the scheduler by hand.
 */
final class SchedulerTick extends Command
{
    protected $signature = 'scheduler:tick';

    protected $description = 'Run every due scheduled task once, most important first, inside the run budget';

    public function handle(SchedulerRunner $runner): int
    {
        $report = $runner->run();
        unset($report['error_messages']);

        $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $report['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
