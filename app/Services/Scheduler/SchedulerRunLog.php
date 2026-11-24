<?php

declare(strict_types=1);

namespace App\Services\Scheduler;

use App\Models\SchedulerRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes one scheduler_runs row per call. A busy run's row is written as "running" first and completed at the end, so
 * a run the platform kills stays visible. Never stops a run: if the table is missing (deployed before migrating) the
 * run goes ahead and a warning is logged.
 */
final class SchedulerRunLog
{
    private const int MAX_MESSAGES = 20;

    private const int MAX_MESSAGE_LENGTH = 300;

    public function start(CarbonImmutable $startedAt): ?SchedulerRun
    {
        return self::guard(static fn(): SchedulerRun => SchedulerRun::query()->create([
            'status' => 'running',
            'started_at' => $startedAt,
        ]));
    }

    /**
     * @param array{
     *     status: 'idle'|'ran'|'locked',
     *     ran: list<string>,
     *     skipped: list<string>,
     *     counts: array<string, array<string, int>>,
     *     errors: int,
     *     error_messages: list<string>,
     *     duration_ms: int,
     *     next_due: list<string>,
     * } $report
     */
    public function finish(?SchedulerRun $run, CarbonImmutable $startedAt, array $report): void
    {
        $values = [
            'status' => $report['status'],
            'started_at' => $startedAt,
            'finished_at' => CarbonImmutable::now(),
            'duration_ms' => $report['duration_ms'],
            'ran' => $report['ran'] === [] ? null : $report['ran'],
            'skipped' => $report['skipped'] === [] ? null : $report['skipped'],
            'counts' => $report['counts'] === [] ? null : $report['counts'],
            'errors' => min(65_535, $report['errors']),
            'error_messages' => $report['error_messages'] === []
                ? null
                : array_map(
                    static fn(string $message): string => Str::limit($message, self::MAX_MESSAGE_LENGTH),
                    array_slice($report['error_messages'], 0, self::MAX_MESSAGES),
                ),
        ];

        self::guard(static function () use ($run, $values): void {
            if ($run === null) {
                SchedulerRun::query()->create($values);

                return;
            }

            $run->update($values);
        });
    }

    /**
     * @template T
     *
     * @param callable(): T $write
     *
     * @return T|null
     */
    private static function guard(callable $write): mixed
    {
        try {
            return $write();
        } catch (QueryException $exception) {
            Log::warning('Could not record the scheduler run; run the migrations (scheduler_runs).', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
