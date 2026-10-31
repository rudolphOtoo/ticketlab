<?php

declare(strict_types=1);

test('run scheduler endpoint executes schedule and returns output', function () {
    $response = $this->get('api/run-scheduler');

    $response->assertOk();
    $response->assertJsonStructure(['message', 'output']);
});

test('run scheduler requires token if SCHEDULER_TOKEN is configured in env', function () {
    putenv('SCHEDULER_TOKEN=test-secret-token');

    $unauthorized = $this->get('api/run-scheduler');
    $unauthorized->assertStatus(401);

    $authorized = $this->get('api/run-scheduler?token=test-secret-token');
    $authorized->assertOk();

    putenv('SCHEDULER_TOKEN');
});

test('run migrate endpoint requires secret token', function () {
    $unauthorized = $this->get('api/run-migrate');
    $unauthorized->assertStatus(401);
});
