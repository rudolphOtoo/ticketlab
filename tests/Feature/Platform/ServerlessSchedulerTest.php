<?php

declare(strict_types=1);

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
