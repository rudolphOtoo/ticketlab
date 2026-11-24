<?php

declare(strict_types=1);

namespace App\Http\Controllers\Maintenance;

use App\Services\Scheduler\SchedulerRunner;
use Illuminate\Http\JsonResponse;

/**
 * Called by cron-job.org every 5 minutes. Always 200 with JSON once authorised (401, 429 and 503 come from the
 * middleware): {"status": "idle"|"ran"|"locked", "ran": [...], "skipped": [...], "counts": {...}, "errors": n,
 * "duration_ms": n, "next_due": [...]}. Error details stay in the logs, never in the response a cron service stores.
 */
final class RunSchedulerController
{
    public function __invoke(SchedulerRunner $runner): JsonResponse
    {
        // A cron that gives up waiting (its own timeout) must not cut a run short half-way through a task.
        ignore_user_abort(true);

        $report = $runner->run();
        unset($report['error_messages']);
        // Objects in JSON even when empty: "counts": {} and "counts": {"worker": {}}.
        $report['counts'] = (object) array_map(static fn(array $counts): object => (object) $counts, $report['counts']);

        return response()->json($report)->header('Cache-Control', 'no-store');
    }
}
