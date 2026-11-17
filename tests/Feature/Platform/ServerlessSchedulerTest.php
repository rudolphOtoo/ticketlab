<?php

declare(strict_types=1);

test('run scheduler refuses every request while no token is configured', function () {
    config(['services.scheduler.token' => null]);

    $this->get('api/run-scheduler')->assertUnauthorized();
    $this->get('api/run-scheduler?token=')->assertUnauthorized();
});

test('run scheduler executes the schedule only with the configured token', function () {
    config(['services.scheduler.token' => 'test-secret-token']);

    $this->get('api/run-scheduler')->assertUnauthorized();
    $this->get('api/run-scheduler?token=wrong-token')->assertUnauthorized();

    $this->get('api/run-scheduler?token=test-secret-token')->assertOk()->assertJsonStructure(['message', 'output']);
});

test('run migrate endpoint requires secret token', function () {
    config(['services.scheduler.token' => 'test-secret-token']);

    $this->get('api/run-migrate')->assertUnauthorized();
    $this->get('api/run-migrate?token=wrong-token')->assertUnauthorized();
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
