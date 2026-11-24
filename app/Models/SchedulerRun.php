<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One scheduler call: what ran, what it did, and how long it took (App\Services\Scheduler\SchedulerRunner).
 *
 * @property int $id
 * @property string $status idle, locked, ran, or running (never finished)
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int|null $duration_ms
 * @property list<string>|null $ran
 * @property list<string>|null $skipped
 * @property array<string, array<string, int>>|null $counts
 * @property int $errors
 * @property list<string>|null $error_messages
 */
class SchedulerRun extends Model
{
    /** A run that got through: nothing was due, or the due tasks ran (errors in single items included). */
    public const array COMPLETED = ['idle', 'ran'];

    public $timestamps = false;

    protected $fillable = [
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'ran',
        'skipped',
        'counts',
        'errors',
        'error_messages',
    ];

    /**
     * @param Builder<SchedulerRun> $query
     *
     * @return Builder<SchedulerRun>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', self::COMPLETED);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'ran' => 'array',
            'skipped' => 'array',
            'counts' => 'array',
            'errors' => 'integer',
            'error_messages' => 'array',
        ];
    }
}
