<?php

declare(strict_types=1);

use App\Http\Middleware\RemovePoweredByHeader;
use Illuminate\Http\Request;

/*
 | Machine endpoints answer without CORS, and no response names the PHP version.
 */

beforeEach(function (): void {
    config([
        'services.scheduler.token' => str_repeat('t', 64),
        'services.scheduler.per_minute' => 100,
    ]);
});

test('the maintenance hooks send no CORS headers, to a cross-origin GET or a preflight', function (string $path) {
    $headers = ['Authorization' => 'Bearer ' . str_repeat('t', 64), 'Origin' => 'https://evil.example'];

    $get = $this->getJson($path, $headers);
    $preflight = $this->call(
        'OPTIONS',
        $path,
        [],
        [],
        [],
        [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ],
    );

    foreach ([$get, $preflight] as $response) {
        expect($response->headers->has('Access-Control-Allow-Origin'))
            ->toBeFalse()
            ->and($response->headers->has('Access-Control-Allow-Methods'))
            ->toBeFalse();
    }
})->with(['api/run-scheduler', 'api/run-migrate']);

test('no route is opened to other origins', function () {
    expect(config('cors.paths'))->toBe([])->and(config('cors.allowed_origins'))->toBe([]);
});

test('no response carries X-Powered-By, even when something set it', function () {
    // Runs on every request, the machine endpoints outside the web group included.
    expect(app(Illuminate\Contracts\Http\Kernel::class)->hasMiddleware(RemovePoweredByHeader::class))->toBeTrue();

    $stripped = new RemovePoweredByHeader()->handle(Request::create('/'), static fn() => response('ok')->header(
        'X-Powered-By',
        'PHP/8.5.12',
    ));
    expect($stripped->headers->has('X-Powered-By'))->toBeFalse();

    $this->getJson('api/run-scheduler', ['Authorization' => 'Bearer ' . str_repeat('t', 64)])->assertHeaderMissing(
        'X-Powered-By',
    );
    $this->get('/')->assertHeaderMissing('X-Powered-By');
});

test('the Vercel functions turn expose_php off', function () {
    $ini = parse_ini_file(base_path('api/php.ini'));

    expect($ini)
        ->toBeArray()
        ->and($ini['expose_php'] ?? null)
        ->toBe('');
});
