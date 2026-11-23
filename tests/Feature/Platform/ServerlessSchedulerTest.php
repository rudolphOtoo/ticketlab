<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

test('the maintenance endpoints refuse every request while no token is configured', function (string $path) {
    config(['services.scheduler.token' => null]);

    $this->get($path)->assertUnauthorized();
    $this->get($path, ['Authorization' => 'Bearer '])->assertUnauthorized();
    $this->get($path . '?token=')->assertUnauthorized();
})->with(['api/run-scheduler', 'api/run-migrate']);

test('the scheduler runs with the token in an Authorization Bearer header', function () {
    config(['services.scheduler.token' => 'test-secret-token']);
    Log::spy();

    $this->get('api/run-scheduler')->assertUnauthorized();
    $this->get('api/run-scheduler', ['Authorization' => 'Bearer wrong-token'])->assertUnauthorized();

    $this
        ->get('api/run-scheduler', ['Authorization' => 'Bearer test-secret-token'])
        ->assertOk()
        ->assertJsonStructure(['message', 'output']);

    Log::shouldNotHaveReceived('warning');
});

test('a wrong bearer token is refused even when a valid query token is also sent', function () {
    config(['services.scheduler.token' => 'test-secret-token']);

    $this->get('api/run-scheduler?token=test-secret-token', [
        'Authorization' => 'Bearer wrong-token',
    ])->assertUnauthorized();
});

test('the query token still works for one release but logs a deprecation without the token', function () {
    config(['services.scheduler.token' => 'test-secret-token']);
    Log::spy();

    $this->get('api/run-scheduler?token=wrong-token')->assertUnauthorized();
    $this->get('api/run-scheduler?token=test-secret-token')->assertOk();

    Log::shouldHaveReceived('warning')
        ->withArgs(
            static fn(string $message, array $context): bool => (
                str_starts_with($message, 'Deprecated: scheduler token sent as ?token=')
                && !str_contains($message . json_encode($context), 'test-secret-token')
            ),
        )
        ->once();
});

test('run migrate requires the token too', function () {
    config(['services.scheduler.token' => 'test-secret-token']);

    $this->get('api/run-migrate')->assertUnauthorized();
    $this->get('api/run-migrate', ['Authorization' => 'Bearer wrong-token'])->assertUnauthorized();
    $this
        ->get('api/run-migrate', ['Authorization' => 'Bearer test-secret-token'])
        ->assertOk()
        ->assertJsonStructure(['message', 'output']);
});

test('the scheduled worker runs every queue that jobs are dispatched onto', function () {
    $dispatched = ['default'];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all(
            "/onQueue\\(\\s*'([^']+)'\\s*\\)|\\\$queue\\s*=\\s*'([^']+)'/",
            (string) file_get_contents($file->getPathname()),
            $matches,
        );
        $dispatched = [...$dispatched, ...array_filter($matches[1]), ...array_filter($matches[2])];
    }

    $worker = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->map(static fn($event): string => (string) $event->command)
        ->first(static fn(string $command): bool => str_contains($command, 'queue:work'));

    expect($worker)->not->toBeNull();
    preg_match('/--queue=([\w,-]+)/', (string) $worker, $queues);
    $worked = explode(',', $queues[1] ?? '');

    expect(array_values(array_diff(array_unique($dispatched), $worked)))
        ->toBe([])
        ->and($dispatched)
        ->toContain('transactional', 'broadcasts');
});

describe('cron-job.org every 5 minutes', function (): void {
    beforeEach(function (): void {
        config(['services.scheduler.token' => 'test-secret-token']);
    });

    test('an idle call answers at once without running schedule:run, and starts no session', function () {
        $response = $this
            ->get('api/run-scheduler', ['Authorization' => 'Bearer test-secret-token'])
            ->assertOk()
            ->assertExactJson(['message' => 'Nothing due', 'due' => [], 'output' => '']);

        // Outside the "web" group: no session or cookies for a machine caller.
        expect($response->headers->getCookies())->toBe([]);
    });

    test('a call with work due runs the scheduler and says what was due', function () {
        App\Models\Reservation::factory()->create([
            'status' => App\Enums\ReservationStatus::Active,
            'expires_at' => now()->subMinute(),
        ]);
        // The factory's zones count as checked, so only the expired hold is due.
        App\Models\Subdivision::query()->update(['payout_checked_at' => now()]);

        $this
            ->get('api/run-scheduler', ['Authorization' => 'Bearer test-secret-token'])
            ->assertOk()
            ->assertJsonPath('message', 'Scheduler executed')
            ->assertJsonPath('due', ['expired-holds']);

        // The run's deadline is gone again afterwards.
        expect(App\Support\Scheduler\SchedulerBudget::secondsLeft())->toBeNull();
    });
});

test('each kind of scheduled work is detected only when it is due', function () {
    expect(App\Support\Scheduler\SchedulerWork::due())->toBe([]);

    // A pending order is the webhook's and the buyer's for its first 10 minutes.
    $order = App\Models\Order::factory()->create();
    App\Models\Subdivision::query()->update(['payout_checked_at' => now()]);
    expect(App\Support\Scheduler\SchedulerWork::due())->toBe([]);
    $this->travel(11)->minutes();
    expect(App\Support\Scheduler\SchedulerWork::due())->toBe(['pending-payments']);
    $order->update(['payment_status' => App\Enums\OrderStatus::Paid]);

    // An owed ticket email.
    $order->forceFill(['ticket_email_due_at' => now()])->save();
    expect(App\Support\Scheduler\SchedulerWork::due())->toBe(['ticket-emails']);
    $order->forceFill(['ticket_email_due_at' => now()->addMinutes(5)])->save();
    expect(App\Support\Scheduler\SchedulerWork::due())->toBe([]);

    // A queued job that is available now, but not one delayed to tomorrow.
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert([
        'queue' => 'broadcasts',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->addDay()->getTimestamp(),
        'created_at' => now()->getTimestamp(),
    ]);
    expect(App\Support\Scheduler\SchedulerWork::due())->toBe([]);
    DB::table('jobs')->update(['available_at' => now()->getTimestamp()]);
    expect(App\Support\Scheduler\SchedulerWork::due())->toBe(['queued-jobs']);
});
