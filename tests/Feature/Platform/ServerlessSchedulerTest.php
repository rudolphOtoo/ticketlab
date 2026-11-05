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
