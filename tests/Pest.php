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

/**
 * Paystack recognises these subaccount codes as active GHS subaccounts for the suite's key.
 *
 * Checkout and publishing verify every zone's code through the real resolver, so tests that are not about payout
 * verification mark their zones payable with this instead of bypassing the check.
 */
function paystack_accepts_subaccounts(string ...$codes): void
{
    $stubs = [];

    foreach ($codes as $code) {
        $stubs['api.paystack.co/subaccount/' . $code] = Http::response([
            'status' => true,
            'message' => 'Subaccount retrieved',
            'data' => ['subaccount_code' => $code, 'active' => true, 'currency' => 'GHS'],
        ]);
    }

    Http::fake($stubs);
}

/**
 * Every zone of the organisation has a payout subaccount verified under the suite's (test) key, stored the same
 * way saving a code in the admin does. Checkout reads that stored verification and never calls Paystack.
 */
function make_zones_payable(App\Models\Organization $organization): void
{
    $codes = [];

    foreach (App\Models\Subdivision::query()->where('organization_id', $organization->id)->get() as $zone) {
        if (blank($zone->paystack_subaccount_code)) {
            $zone->update(['paystack_subaccount_code' => 'ACCT_' . strtolower(Illuminate\Support\Str::random(12))]);
        }

        $codes[] = (string) $zone->paystack_subaccount_code;
        app(App\Services\Payment\PaystackSubaccountResolver::class)->markVerified($zone->refresh());
    }

    paystack_accepts_subaccounts(...$codes);
}

/**
 * A zone that can take payments: created with a subaccount code Paystack recognises.
 */
function payable_zone(App\Models\Organization $organization, string $name = 'Main Zone'): App\Models\Subdivision
{
    $zone = App\Models\Subdivision::factory()->create([
        'organization_id' => $organization->id,
        'name' => $name,
        'paystack_subaccount_code' => 'ACCT_' . strtolower(Illuminate\Support\Str::random(12)),
    ]);

    paystack_accepts_subaccounts((string) $zone->paystack_subaccount_code);
    app(App\Services\Payment\PaystackSubaccountResolver::class)->markVerified($zone);

    return $zone->refresh();
}

/**
 * The routing part of a successful Paystack charge for this order: settled into the zone subaccount that
 * checkout recorded, with the zone's share equal to the ticket value. Empty for unrouted (free or legacy) orders.
 *
 * @return array<string, mixed>
 */
function paystack_routing(App\Models\Order $order): array
{
    $code = $order->fresh()?->payout_subaccount_code;

    return (
        $code === null
            ? []
            : [
                'subaccount' => ['subaccount_code' => $code],
                'fees_split' => ['subaccount' => App\Services\PaystackFeeCalculator::toPesewas($order->net_amount)],
            ]
    );
}
