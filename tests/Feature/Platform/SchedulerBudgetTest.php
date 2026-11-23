<?php

declare(strict_types=1);

use App\Support\Scheduler\SchedulerBudget;
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

    expect($worker)->toContain('--stop-when-empty')->toContain('--max-time=12')->toContain('--max-jobs=50');
});

test('every task runs on every scheduler call, most important first, with the worker last', function () {
    $commands = array_map(
        static fn(Event $event): string => (string) preg_replace('/^.*artisan\S*\s+/', '', (string) $event->command),
        app(Schedule::class)->events(),
    );

    // cron-job.org calls every 5 minutes; a wall-clock expression such as "0 * * * *" would be skipped by a late call.
    foreach (app(Schedule::class)->events() as $event) {
        expect($event->expression)->toBe('* * * * *');
    }

    expect(array_map(static fn(string $command): string => strtok($command, ' '), $commands))->toBe([
        'reservations:release-expired',
        'tickets:email-pending',
        'payments:reconcile-pending',
        'payouts:recheck-subaccounts',
        'queue:work',
    ]);
});

test('a scheduler run has one deadline under the function limit, and each budget is cut to what is left', function () {
    $vercel = json_decode((string) file_get_contents(base_path('vercel.json')), true);

    expect($vercel['functions']['api/index.php']['maxDuration'])
        ->toBe(config('scheduler.function_max_duration'))
        ->and(config('scheduler.run_budget_seconds'))
        ->toBeLessThanOrEqual(config('scheduler.function_max_duration') - 10);

    // Outside a run (a manual command): the configured budget.
    expect(SchedulerBudget::clamp(12))->toBe(12)->and(SchedulerBudget::allows(1_000))->toBeTrue();

    SchedulerBudget::start(20);

    try {
        expect(SchedulerBudget::clamp(12))->toBe(12)->and(SchedulerBudget::clamp(30))->toBeLessThanOrEqual(20);

        // The run's time is up (time() is real, so set a deadline in the past).
        putenv(SchedulerBudget::VARIABLE . '=' . (time() - 1));

        expect(SchedulerBudget::clamp(12))->toBe(0)->and(SchedulerBudget::allows(1))->toBeFalse();
    } finally {
        SchedulerBudget::clear();
    }

    expect(SchedulerBudget::secondsLeft())->toBeNull();
});
