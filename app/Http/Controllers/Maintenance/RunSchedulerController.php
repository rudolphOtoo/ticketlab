<?php

declare(strict_types=1);

namespace App\Http\Controllers\Maintenance;

use App\Support\Scheduler\SchedulerBudget;
use App\Support\Scheduler\SchedulerWork;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * Called by cron-job.org every 5 minutes. Answers at once when nothing is due; otherwise runs `schedule:run` inside
 * a run deadline that keeps the whole request under Vercel's 60-second limit (config/scheduler.php).
 */
final class RunSchedulerController
{
    public function __invoke(): JsonResponse
    {
        $due = SchedulerWork::due();

        if ($due === []) {
            return response()->json(['message' => 'Nothing due', 'due' => [], 'output' => '']);
        }

        SchedulerBudget::start((int) config('scheduler.run_budget_seconds'));

        try {
            Artisan::call('schedule:run');
        } finally {
            SchedulerBudget::clear();
        }

        return response()->json(['message' => 'Scheduler executed', 'due' => $due, 'output' => Artisan::output()]);
    }
}
