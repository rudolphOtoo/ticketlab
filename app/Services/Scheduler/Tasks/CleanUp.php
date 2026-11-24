<?php

declare(strict_types=1);

namespace App\Services\Scheduler\Tasks;

use App\Models\SchedulerRun;
use App\Services\Scheduler\SchedulerTask;
use App\Services\Scheduler\TaskResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Housekeeping, last and least important, a bounded batch per run: scheduler_runs rows older than keep_runs_days, and
 * cache and lock rows of the database cache store that expired more than expired_cache_grace_hours ago (throttle
 * counters, run locks). Due rarely, so an idle call stays idle.
 */
final class CleanUp implements SchedulerTask
{
    public function name(): string
    {
        return 'cleanup';
    }

    public function isDue(): bool
    {
        if (self::oldRunsExist()) {
            return true;
        }

        foreach ($this->expiredCacheRows() as $rows) {
            if ($rows->exists()) {
                return true;
            }
        }

        return false;
    }

    public function run(CarbonImmutable $deadline): TaskResult
    {
        $result = new TaskResult(['scheduler_runs' => 0, 'cache_rows' => 0]);
        $batch = max(1, (int) config('scheduler.cleanup.batch_size'));

        if (self::oldRunsExist()) {
            $runIds = self::oldRuns()->limit($batch)->pluck('id');
            $result->add('scheduler_runs', SchedulerRun::query()->whereKey($runIds)->delete());
        }

        foreach ($this->expiredCacheRows() as $rows) {
            if (CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
                break;
            }

            $keys = (clone $rows)->limit($batch)->pluck('key');
            $result->add('cache_rows', $rows->whereIn('key', $keys)->delete());
        }

        return $result;
    }

    /** False too while scheduler_runs does not exist yet (deployed before migrating; SchedulerRunLog warns). */
    private static function oldRunsExist(): bool
    {
        try {
            return self::oldRuns()->exists();
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * @return EloquentBuilder<SchedulerRun>
     */
    private static function oldRuns(): EloquentBuilder
    {
        return SchedulerRun::query()->where(
            'started_at',
            '<',
            now()->subDays((int) config('scheduler.cleanup.keep_runs_days')),
        );
    }

    /**
     * Only when a database cache store is configured (it holds the run lock and the throttle counters).
     *
     * @return list<Builder>
     */
    private function expiredCacheRows(): array
    {
        $store = config('cache.stores.database');

        if (!is_array($store) || ($store['driver'] ?? null) !== 'database') {
            return [];
        }

        $before = now()->subHours((int) config('scheduler.cleanup.expired_cache_grace_hours'))->getTimestamp();
        $connection = DB::connection(is_string($store['connection'] ?? null) ? $store['connection'] : null);

        return array_map(
            static fn(string $table): Builder => $connection->table($table)->where('expiration', '<', $before),
            [(string) ($store['table'] ?? 'cache'), (string) ($store['lock_table'] ?? 'cache_locks')],
        );
    }
}
