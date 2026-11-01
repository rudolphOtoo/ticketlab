<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Livewire\Admin\ZonePayouts;
use App\Livewire\Public\EventShow;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const ZONE_SUBACCOUNT = 'ACCT_2qlhkjrzdd16h0m';

beforeEach(function (): void {
    config([
        'services.paystack.split_payments' => true,
        'services.paystack.unrouted_zone_policy' => 'fallback',
        'services.paystack.secret_key' => 'sk_test_zones',
    ]);

    // A platform fee of GHS 2: GHS 50 ticket + 2 grossed up for the 1.95% processing fee = GHS 53.04.
    $this->org = Organization::factory()->create(['settings' => ['platform_fee' => 2]]);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'status' => 'published']);
    $this->ticket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'price' => 50,
        'available_quantity' => 10,
    ]);
    $this->zone = Subdivision::factory()->create([
        'organization_id' => $this->org->id,
        'name' => 'Zone B',
        'paystack_subaccount_code' => ZONE_SUBACCOUNT,
    ]);

    // Paystack only knows the subaccounts created on this test account.
    $known = [
        ZONE_SUBACCOUNT => [],
        'ACCT_e3wn0gw6j4rnegl' => [],
        'ACCT_deactivated1' => ['active' => false],
        'ACCT_nairazone001' => ['currency' => 'NGN'],
    ];

    Http::fake([
        'api.paystack.co/subaccount/*' => static fn(Request $request) => (
            array_key_exists(basename($request->url()), $known)
                ? Http::response([
                    'status' => true,
                    'message' => 'Subaccount retrieved',
                    'data' => [
                        'business_name' => 'Zone B',
                        'settlement_bank' => 'MTN',
                        'account_number' => '0551234987',
                        'currency' => 'GHS',
                        'active' => true,
                        ...$known[basename($request->url())],
                    ],
                ])
                : Http::response(['status' => false, 'message' => 'Subaccount not found'], 404)
        ),
    ]);
});

function buy_ticket(object $test, ?Subdivision $zone, int $quantity = 1): Testable
{
    return Livewire::test(EventShow::class, ['org_slug' => $test->org->slug, 'event_slug' => $test->event->slug])
        ->set('ticketTypeId', $test->ticket->id)
        ->set('quantity', $quantity)
        ->set('subdivisionId', $zone?->id)
        ->set('customerName', 'Esi Owusu')
        ->set('customerEmail', 'esi@example.com')
        ->set('customerPhone', '0244000111')
        ->call('startCheckout');
}

/**
 * @return list<array<string, mixed>>
 */
function initializations(): array
{
    return Http::recorded(static fn(Request $request): bool => str_ends_with(
        $request->url(),
        '/transaction/initialize',
    ))
        ->map(static fn(array $pair): array => $pair[0]->data())
        ->values()
        ->all();
}

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function settled_payment(Order $order, array $overrides = []): array
{
    return [
        'reference' => $order->gatewayReference(),
        'status' => 'success',
        'amount' => $order->gross_pesewas,
        'currency' => 'GHS',
        'channel' => 'mobile_money',
        'subaccount' => ['subaccount_code' => ZONE_SUBACCOUNT],
        'fees_split' => ['paystack' => 103, 'integration' => 201, 'subaccount' => 5000],
        ...$overrides,
    ];
}

function post_webhook(object $test, array $data): \Illuminate\Testing\TestResponse
{
    $payload = ['event' => 'charge.success', 'data' => $data];
    $body = (string) json_encode($payload);

    return $test->call(
        'POST',
        '/api/paystack/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_zones'),
        ],
        $body,
    );
}

test('the zone receives exactly the ticket value and the main account keeps fees', function (): void {
    buy_ticket($this, $this->zone)
        ->assertHasNoErrors()
        ->assertDispatched('open-paystack', accessCode: 'ac_' . substr(sha1(Order::query()->sole()->reference), 0, 12));

    $order = Order::query()->sole();

    expect(initializations())
        ->toHaveCount(1)
        ->and(initializations()[0])
        ->toMatchArray([
            'email' => 'esi@example.com',
            'amount' => 5304,
            'currency' => 'GHS',
            'reference' => $order->reference,
            'subaccount' => ZONE_SUBACCOUNT,
            'transaction_charge' => 304,
            'bearer' => 'account',
        ])
        ->and($order->payout_subaccount_code)
        ->toBe(ZONE_SUBACCOUNT)
        ->and($order->metadata['paystack']['mode'])
        ->toBe('test');
});

test('reopening an unchanged checkout resumes the same paystack transaction', function (): void {
    $checkout = buy_ticket($this, $this->zone);
    $checkout->call('startCheckout')->assertHasNoErrors();

    expect(initializations())->toHaveCount(1);
});

test('changing the order opens a fresh paystack reference for it', function (): void {
    $checkout = buy_ticket($this, $this->zone);
    $checkout->set('quantity', 2)->call('startCheckout')->assertHasNoErrors();

    $order = Order::query()->sole();

    expect(initializations())
        ->toHaveCount(2)
        ->and(initializations()[1]['reference'])
        ->toBe($order->reference . '.2')
        ->and(initializations()[1]['amount'])
        ->toBe(10_403)
        ->and($order->gatewayReference())
        ->toBe($order->reference . '.2');
});

test('a reference paystack has already seen is retried under the next attempt', function (): void {
    // e.g. a pending order whose reference was already used by the old browser-side popup.
    paystack_initialize_responds(['status' => false, 'message' => 'Duplicate Transaction Reference']);

    buy_ticket($this, $this->zone)->assertHasNoErrors();
    $order = Order::query()->sole();

    expect(array_column(initializations(), 'reference'))
        ->toBe([$order->reference, $order->reference . '.2'])
        ->and($order->gatewayReference())
        ->toBe($order->reference . '.2');
});

test('a transaction paystack refuses surfaces a payment error to the buyer', function (): void {
    paystack_initialize_responds(['status' => false, 'message' => 'Invalid Subaccount.']);
    Log::spy();

    buy_ticket($this, $this->zone)->assertHasErrors('payment')->assertNotDispatched('open-paystack');

    Log::shouldHaveReceived('error')
        ->withArgs(
            static fn(string $message, array $context): bool => (
                str_contains($message, 'refused to initialise')
                && $context['paystack_message'] === 'Invalid Subaccount.'
            ),
        )
        ->once();
});

test('a block policy refuses checkout for a zone without a verified subaccount', function (): void {
    config(['services.paystack.unrouted_zone_policy' => 'block']);
    $this->zone->update(['paystack_subaccount_code' => null]);

    buy_ticket($this, $this->zone)->assertHasErrors('payment');

    expect(initializations())->toBe([]);
});

test('the fallback policy collects centrally and flags the sale as owed to the zone', function (): void {
    $this->zone->update(['paystack_subaccount_code' => null]);
    Log::spy();

    buy_ticket($this, $this->zone)->assertHasNoErrors();

    expect(initializations()[0])
        ->not
        ->toHaveKey('subaccount')
        ->and(Order::query()->sole()->payout_subaccount_code)
        ->toBeNull();
    Log::shouldHaveReceived('warning')
        ->withArgs(static fn(string $message): bool => str_contains($message, 'no verified payout subaccount'))
        ->once();
});

test('a payment that settled to the zone subaccount is fulfilled with its split recorded', function (): void {
    buy_ticket($this, $this->zone);
    $order = Order::query()->sole();

    post_webhook($this, settled_payment($order))->assertOk()->assertJson(['status' => 'success']);

    $order->refresh();
    expect($order->payment_status)
        ->toBe(OrderStatus::Paid)
        ->and($order->metadata['payout_share_pesewas'])
        ->toBe(5000)
        ->and($order->metadata['platform_share_pesewas'])
        ->toBe(201)
        ->and($order->attendees)
        ->toHaveCount(1);
});

test('payments that did not settle to the expected zone account are held for review', function (array $overrides): void {
    buy_ticket($this, $this->zone);
    $order = Order::query()->sole();

    post_webhook($this, settled_payment($order, $overrides))->assertStatus(409);

    expect($order->refresh()->payment_status)
        ->toBe(OrderStatus::RequiresReview)
        ->and($order->attendees()->count())
        ->toBe(0);
})->with([
    'different subaccount' => [['subaccount' => ['subaccount_code' => 'ACCT_someoneelse1']]],
    'no subaccount' => [['subaccount' => []]],
    'zone short-changed' => [['fees_split' => ['paystack' => 103, 'integration' => 1201, 'subaccount' => 4000]]],
]);

test('an unrouted order paid into a subaccount is held for review', function (): void {
    $this->zone->update(['paystack_subaccount_code' => null]);
    buy_ticket($this, $this->zone);
    $order = Order::query()->sole();

    post_webhook($this, settled_payment($order))->assertStatus(409);

    expect($order->refresh()->payment_status)->toBe(OrderStatus::RequiresReview);
});

test('a payment under a re-opened attempt reference fulfils the order', function (): void {
    $checkout = buy_ticket($this, $this->zone);
    $checkout->set('quantity', 2)->call('startCheckout');
    $order = Order::query()->sole();

    post_webhook($this, settled_payment($order, ['fees_split' => ['subaccount' => 10_000]]))->assertOk();

    expect($order->refresh()->payment_status)->toBe(OrderStatus::Paid)->and($order->attendees)->toHaveCount(2);
});

test('paying an abandoned earlier attempt is held for review instead of fulfilled', function (): void {
    $checkout = buy_ticket($this, $this->zone);
    $order = Order::query()->sole();
    $firstReference = $order->reference;
    $checkout->set('quantity', 2)->call('startCheckout');

    post_webhook($this, settled_payment($order->refresh(), ['reference' => $firstReference]))->assertStatus(409);

    expect($order->refresh()->payment_status)->toBe(OrderStatus::RequiresReview);
});

test('a routed webhook without split details is confirmed against the paystack record', function (): void {
    buy_ticket($this, $this->zone);
    $order = Order::query()->sole();
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
        'status' => true,
        'data' => settled_payment($order),
    ])]);

    $payment = settled_payment($order);
    unset($payment['subaccount'], $payment['fees_split']);

    post_webhook($this, $payment)->assertOk();

    expect($order->refresh()->payment_status)->toBe(OrderStatus::Paid);
    Http::assertSent(static fn(Request $request): bool => str_contains(
        $request->url(),
        '/transaction/verify/' . $order->reference,
    ));
});

test('a second successful payment for a settled order raises a refund alert', function (): void {
    $checkout = buy_ticket($this, $this->zone);
    $order = Order::query()->sole();
    $firstReference = $order->reference;
    post_webhook($this, settled_payment($order))->assertOk();
    Log::spy();

    post_webhook($this, settled_payment($order->refresh(), ['reference' => $firstReference . '.7']))->assertOk();

    Log::shouldHaveReceived('critical')
        ->withArgs(static fn(string $message): bool => str_contains($message, 'must be refunded'))
        ->once();
});

test('checkout attempts are rate limited per client', function (): void {
    foreach (range(1, 15) as $attempt) {
        RateLimiter::hit('checkout:127.0.0.1', 600);
    }

    buy_ticket($this, $this->zone)->assertHasErrors('payment');

    expect(initializations())->toBe([])->and(Order::query()->count())->toBe(0);
});

test('a stale paystack session is not resumed', function (): void {
    $checkout = buy_ticket($this, $this->zone);
    $order = Order::query()->sole();
    $order->forceFill([
        'metadata' => [
            ...$order->metadata,
            'paystack' => [...$order->metadata['paystack'], 'initialized_at' => now()->subHours(2)->format(DATE_ATOM)],
        ],
    ])->save();

    $checkout->call('startCheckout')->assertHasNoErrors();

    expect(array_column(initializations(), 'reference'))->toBe([
        Order::query()->sole()->reference,
        Order::query()->sole()->reference . '.2',
    ]);
});

test('subaccounts paystack cannot settle into are not routed', function (string $code): void {
    $this->zone->update(['paystack_subaccount_code' => $code]);

    buy_ticket($this, $this->zone)->assertHasNoErrors();

    expect(initializations()[0])
        ->not
        ->toHaveKey('subaccount')
        ->and(Order::query()->sole()->payout_subaccount_code)
        ->toBeNull();
})->with(['deactivated' => ['ACCT_deactivated1'], 'wrong currency' => ['ACCT_nairazone001']]);

/*
 |--------------------------------------------------------------------------
 | Admin payout onboarding & per-zone ledger
 |--------------------------------------------------------------------------
 */

test('an organisation admin connects a zone after paystack confirms the subaccount', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $zone = Subdivision::factory()->create(['organization_id' => $this->org->id, 'paystack_subaccount_code' => null]);

    Livewire::actingAs($admin)
        ->test(ZonePayouts::class)
        ->call('editZone', $zone->id)
        ->set('subaccountCode', 'ACCT_e3wn0gw6j4rnegl')
        ->call('saveZone')
        ->assertHasNoErrors()
        ->assertSee('settles to Zone B (MTN)');

    expect($zone->refresh()->paystack_subaccount_code)->toBe('ACCT_e3wn0gw6j4rnegl');
});

test('a subaccount paystack does not recognise is never saved', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $zone = Subdivision::factory()->create(['organization_id' => $this->org->id, 'paystack_subaccount_code' => null]);

    Livewire::actingAs($admin)
        ->test(ZonePayouts::class)
        ->call('editZone', $zone->id)
        ->set('subaccountCode', 'ACCT_unknown123')
        ->call('saveZone')
        ->assertHasErrors('subaccountCode');

    expect($zone->refresh()->paystack_subaccount_code)->toBeNull();
});

test('admins are told why a known subaccount cannot be used', function (string $code, string $reason): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);

    Livewire::actingAs($admin)
        ->test(ZonePayouts::class)
        ->call('editZone', $this->zone->id)
        ->set('subaccountCode', $code)
        ->call('saveZone')
        ->assertHasErrors(['subaccountCode'])
        ->assertSee($reason);

    expect($this->zone->refresh()->paystack_subaccount_code)->toBe(ZONE_SUBACCOUNT);
})->with([
    'deactivated' => ['ACCT_deactivated1', 'deactivated on Paystack'],
    'wrong currency' => ['ACCT_nairazone001', 'settles in NGN'],
]);

test('zones from another organisation cannot be edited', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $foreign = Subdivision::factory()->create(['paystack_subaccount_code' => null]);

    Livewire::actingAs($admin)
        ->test(ZonePayouts::class)
        ->call('editZone', $foreign->id)
        ->assertSet('editingZoneId', null);
});

test('zone-scoped staff cannot redirect payouts', function (): void {
    $zoneAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $this->zone->id);

    $this->actingAs($zoneAdmin)->get(route('admin.payouts'))->assertForbidden();
});

test('the ledger separates revenue settled to zones from revenue owed to them', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $unconnected = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Zone C']);
    $paid = [
        'organization_id' => $this->org->id,
        'payment_status' => OrderStatus::Paid,
        'gross_amount' => 53.04,
        'net_amount' => 50,
    ];

    Order::factory()
        ->count(2)
        ->create([...$paid, 'subdivision_id' => $this->zone->id, 'payout_subaccount_code' => ZONE_SUBACCOUNT]);
    Order::factory()->create([...$paid, 'subdivision_id' => $unconnected->id, 'payout_subaccount_code' => null]);
    Order::factory()->create([...$paid, 'subdivision_id' => $this->zone->id, 'payment_status' => OrderStatus::Pending]);

    $this
        ->actingAs($admin)
        ->get(route('admin.payouts'))
        ->assertOk()
        ->assertSeeInOrder(['Settled to subaccounts', 'GHS 100.00', 'Owed to zones', 'GHS 50.00']);
});
