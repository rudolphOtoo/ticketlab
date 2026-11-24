<?php

declare(strict_types=1);

/*
 | cron-job.org may stop waiting before a busy run ends; the run must still finish.
 */

test('a scheduler call keeps running when the caller hangs up', function () {
    config(['services.scheduler.token' => str_repeat('t', 64)]);
    $before = ignore_user_abort(false);

    try {
        $this->getJson('api/run-scheduler', ['Authorization' => 'Bearer ' . str_repeat('t', 64)])->assertOk();

        expect(ignore_user_abort())->toBe(1);
    } finally {
        ignore_user_abort((bool) $before);
    }
});

test('a refused call does not change that setting', function () {
    config(['services.scheduler.token' => str_repeat('t', 64)]);
    $before = ignore_user_abort(false);

    try {
        $this->getJson('api/run-scheduler')->assertUnauthorized();

        expect(ignore_user_abort())->toBe(0);
    } finally {
        ignore_user_abort((bool) $before);
    }
});
