<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

/*
 | The contract cron-job.org relies on: GET or POST /api/run-scheduler, "Authorization: Bearer" or "?token=", JSON only.
 */

const SCHEDULER_TEST_TOKEN = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

beforeEach(function (): void {
    config([
        'services.scheduler.token' => SCHEDULER_TEST_TOKEN,
        'services.scheduler.allow_query_token' => true,
        // Most tests make several calls; the throttle has its own test.
        'services.scheduler.per_minute' => 100,
    ]);
});

function bearer(#[\SensitiveParameter] string $token = SCHEDULER_TEST_TOKEN): array
{
    return ['Authorization' => 'Bearer ' . $token];
}

test('the token in an Authorization Bearer header runs the scheduler, by GET or POST', function () {
    $response = $this->getJson('api/run-scheduler', bearer())->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $this->postJson('api/run-scheduler', [], bearer())->assertOk();
});

test('the token as ?token= works too while the query token is allowed', function () {
    $this->getJson('api/run-scheduler?token=' . SCHEDULER_TEST_TOKEN)->assertOk();
    $this->postJson('api/run-scheduler?token=' . SCHEDULER_TEST_TOKEN)->assertOk();
});

test('the query token is refused once SCHEDULER_ALLOW_QUERY_TOKEN is off; the header still works', function () {
    config(['services.scheduler.allow_query_token' => false]);

    $this->getJson('api/run-scheduler?token=' . SCHEDULER_TEST_TOKEN)->assertUnauthorized();
    $this->getJson('api/run-scheduler', bearer())->assertOk();
});

test('a missing or wrong token is refused with JSON', function () {
    $this->getJson('api/run-scheduler')->assertUnauthorized()->assertJsonStructure(['error']);
    $this->getJson('api/run-scheduler', bearer(strrev(SCHEDULER_TEST_TOKEN)))->assertUnauthorized();
    $this->getJson('api/run-scheduler?token=' . strrev(SCHEDULER_TEST_TOKEN))->assertUnauthorized();
    $this->getJson('api/run-scheduler?token=')->assertUnauthorized();
});

test('an empty or short SCHEDULER_TOKEN refuses every call, even one that sends that same value', function (?string $configured) {
    config(['services.scheduler.token' => $configured]);

    $this->getJson('api/run-scheduler', bearer((string) $configured))->assertUnauthorized();
    $this->getJson('api/run-scheduler?token=' . $configured)->assertUnauthorized();
    $this->getJson('api/run-migrate', bearer((string) $configured))->assertUnauthorized();
})->with([
    'unset' => [null],
    'empty' => [''],
    '31 characters' => [str_repeat('a', 31)],
]);

test('a wrong header is refused even when the query token is right', function () {
    $this->getJson(
        'api/run-scheduler?token=' . SCHEDULER_TEST_TOKEN,
        bearer('wrong-' . SCHEDULER_TEST_TOKEN),
    )->assertUnauthorized();
});

test('run-migrate takes the header only, never ?token=', function () {
    $this->getJson('api/run-migrate?token=' . SCHEDULER_TEST_TOKEN)->assertUnauthorized();
    $this->getJson('api/run-migrate', bearer('wrong'))->assertUnauthorized();
    $this->getJson('api/run-migrate', bearer())->assertOk()->assertJsonStructure(['message', 'output']);
});

test('at most 2 calls a minute per IP, so cron retries never stack runs', function () {
    config(['services.scheduler.per_minute' => 2]);

    $this->getJson('api/run-scheduler', bearer())->assertOk();
    $this->getJson('api/run-scheduler', bearer())->assertOk();
    $this->getJson('api/run-scheduler', bearer())->assertTooManyRequests()->assertJsonStructure(['message']);

    // Wrong tokens count too, so the limit also slows guessing.
    $this->getJson('api/run-scheduler', bearer('wrong'))->assertTooManyRequests();

    $this->travel(61)->seconds();
    $this->getJson('api/run-scheduler', bearer())->assertOk();
});

test('in maintenance mode the endpoint answers 503 in JSON, so the cron shows the call as failed', function () {
    // Not the default file driver: storage/framework/down is shared by every parallel test process.
    config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
    app()->maintenanceMode()->activate([]);

    try {
        $this->get('api/run-scheduler', bearer())->assertServiceUnavailable()->assertHeader(
            'Content-Type',
            'application/json',
        );
    } finally {
        app()->maintenanceMode()->deactivate();
    }
});

test('no session, cookies, CSRF or HTML: the endpoint stays outside the web middleware group', function () {
    $response = $this->post('api/run-scheduler', [], bearer())->assertOk();

    expect($response->headers->getCookies())
        ->toBe([])
        ->and($response->headers->get('Content-Type'))
        ->toBe('application/json')
        ->and($response->headers->has('Content-Security-Policy'))
        ->toBeFalse();
});

test('the query token never reaches the logs or the request URL the app sees', function () {
    $logged = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message . json_encode($event->context);
    });

    $this->getJson('api/run-scheduler?token=' . SCHEDULER_TEST_TOKEN . '&probe=1')->assertOk();

    // Other query parameters survive; only the token is gone.
    expect(request()->fullUrl())->not->toContain('token')->toContain('probe=1');

    $this->getJson('api/run-scheduler?token=' . strrev(SCHEDULER_TEST_TOKEN))->assertUnauthorized();

    expect(request()->fullUrl())
        ->not->toContain('token')->and(implode("\n", $logged))
        ->not->toContain(SCHEDULER_TEST_TOKEN)
        ->not->toContain(strrev(SCHEDULER_TEST_TOKEN));
});
