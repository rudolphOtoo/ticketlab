<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Every scheduled task runs inside one serverless request (vercel.json maxDuration: 60 s); see config/scheduler.php.
 */
function scheduled(string $command): Event
{
    $event = collect(app(Schedule::class)->events())
        ->first(static fn(Event $event): bool => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull();

    return $event;
}

test('every scheduled task has a short-lived overlap lock in the shared lock store, not the file store', function () {
    $events = app(Schedule::class)->events();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event->withoutOverlapping)
            ->toBeTrue()
            // Laravel's default lock lasts 24 hours: a request killed by the platform would block the task for a day.
            ->and($event->expiresAt)
            ->toBeLessThanOrEqual(10)
            ->and($event->mutex)
            ->toBeInstanceOf(CacheEventMutex::class)
            ->and($event->mutex->store)
            ->toBe('database')
            ->and(config('cache.stores.database.driver'))
            ->toBe('database');
    }
});

test('a held overlap lock skips the task, and expires on its own after a killed request', function () {
    $reconcile = scheduled('payments:reconcile-pending');

    // First run takes the lock; a second run while it is held is skipped.
    expect($reconcile->shouldSkipDueToOverlapping())
        ->toBeFalse()
        ->and($reconcile->shouldSkipDueToOverlapping())
        ->toBeTrue();

    // The request holding it was killed and never released it: the lock lapses after expiresAt minutes.
    $this->travel($reconcile->expiresAt + 1)->minutes();

    expect($reconcile->shouldSkipDueToOverlapping())->toBeFalse();
});

test('the queue worker stops when empty and within its time and job limits', function () {
    $worker = (string) scheduled('queue:work')->command;

    expect($worker)->toContain('--stop-when-empty')->toContain('--max-time=15')->toContain('--max-jobs=50');
});

test('the worst-case time of tasks that can run in the same minute fits the function limit', function () {
    $worstCase =
        config('scheduler.reconcile.time_budget_seconds')
        + config('scheduler.paystack_timeout_seconds')
        + config('scheduler.payout_recheck.time_budget_seconds')
        + 5
        + config('scheduler.worker.max_time_seconds');

    $vercel = json_decode((string) file_get_contents(base_path('vercel.json')), true);

    expect($vercel['functions']['api/index.php']['maxDuration'])
        ->toBe(config('scheduler.function_max_duration'))
        ->and($worstCase)
        ->toBeLessThan(config('scheduler.function_max_duration'));
});
