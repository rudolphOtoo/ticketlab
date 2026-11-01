<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // Never reach the real Paystack account from the suite, whatever keys the local .env holds.
        config(['services.paystack.secret_key' => 'sk_test_suite', 'services.paystack.public_key' => 'pk_test_suite']);
        app()->instance('paystack.initialize.queue', new ArrayObject());
        Http::preventStrayRequests();
        Http::fake([
            'api.paystack.co/transaction/initialize' => static fn(Request $request) => paystack_initialized($request),
        ]);
    })
    ->in('Feature');

/**
 * Script the next /transaction/initialize answers; once the queue is empty Paystack accepts every request.
 *
 * @param array<string, mixed> ...$bodies
 */
function paystack_initialize_responds(array ...$bodies): void
{
    $queue = app('paystack.initialize.queue');

    foreach ($bodies as $body) {
        $queue->append($body);
    }
}

/**
 * A successful /transaction/initialize answer echoing the requested reference.
 */
function paystack_initialized(Request $request): \GuzzleHttp\Promise\PromiseInterface
{
    $queue = app('paystack.initialize.queue');
    $pending = $queue->getArrayCopy();
    $scripted = array_shift($pending);
    $queue->exchangeArray($pending);

    if (is_array($scripted)) {
        return Http::response($scripted, ($scripted['status'] ?? false) === true ? 200 : 400);
    }

    $reference = (string) $request['reference'];

    return Http::response([
        'status' => true,
        'message' => 'Authorization URL created',
        'data' => [
            'authorization_url' => 'https://checkout.paystack.com/' . sha1($reference),
            'access_code' => 'ac_' . substr(sha1($reference), 0, 12),
            'reference' => $reference,
        ],
    ]);
}
