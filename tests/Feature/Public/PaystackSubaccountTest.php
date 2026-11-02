<?php

declare(strict_types=1);

use App\Livewire\Public\EventShow;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Services\Payment\PaystackSubaccountResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const REAL_SUBACCOUNT = 'ACCT_8f4s1eq7ml6rlzj';

beforeEach(function (): void {
    config([
        'services.paystack.split_payments' => true,
        'services.paystack.secret_key' => 'sk_test_abc123',
        'services.paystack.public_key' => 'pk_test_abc123',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'status' => 'published']);
    $this->ticket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'price' => 50,
        'available_quantity' => 10,
    ]);
});

function checkout_with_zone(object $test, ?Subdivision $zone): Testable
{
    return Livewire::test(EventShow::class, ['org_slug' => $test->org->slug, 'event_slug' => $test->event->slug])
        ->set('ticketTypeId', $test->ticket->id)
        ->set('quantity', 1)
        ->set('subdivisionId', $zone?->id)
        ->set('customerName', 'Ama Mensah')
        ->set('customerEmail', 'ama@example.com')
        ->set('customerPhone', '0244000000')
        ->call('startCheckout')
        ->assertHasNoErrors();
}

function zone_with_code(Organization $org, ?string $code): Subdivision
{
    return Subdivision::factory()->create(['organization_id' => $org->id, 'paystack_subaccount_code' => $code]);
}

/**
 * The transaction was initialised server-side with exactly this payout subaccount (or none).
 */
function initialized_with(?string $subaccount): void
{
    Http::assertSent(
        static fn(Request $request): bool => (
            str_ends_with($request->url(), '/transaction/initialize')
            && ($request->data()['subaccount'] ?? null) === $subaccount
        ),
    );
}

function subaccount_lookups(): int
{
    return Http::recorded(static fn(Request $request): bool => str_contains($request->url(), '/subaccount/'))->count();
}

test('a subaccount verified for the active key is attached to the checkout', function (): void {
    Http::fake(['api.paystack.co/subaccount/*' => Http::response([
        'status' => true,
        'data' => ['subaccount_code' => REAL_SUBACCOUNT],
    ])]);

    checkout_with_zone($this, zone_with_code($this->org, REAL_SUBACCOUNT))->assertDispatched('open-paystack');

    initialized_with(REAL_SUBACCOUNT);
    Http::assertSent(
        static fn(Request $request): bool => $request->url() === 'https://api.paystack.co/subaccount/' . REAL_SUBACCOUNT
        && $request->hasHeader('Authorization', 'Bearer sk_test_abc123'),
    );
});

test('checkout starts without a subaccount when the zone has none', function (?string $code): void {
    checkout_with_zone($this, zone_with_code($this->org, $code))->assertDispatched('open-paystack');

    initialized_with(null);
    expect(subaccount_lookups())->toBe(0);
})->with(['null code' => [null], 'empty code' => ['']]);

test('checkout starts without a zone at all', function (): void {
    checkout_with_zone($this, null)->assertDispatched('open-paystack');

    initialized_with(null);
    expect(subaccount_lookups())->toBe(0);
});

test('a subaccount unknown to the active key environment is omitted and logged', function (): void {
    // e.g. a live subaccount while running on sk_test_ keys: Paystack answers 404 for the test account.
    Http::fake(['api.paystack.co/subaccount/*' => Http::response([
        'status' => false,
        'message' => 'Subaccount not found',
    ], 404)]);
    Log::spy();

    checkout_with_zone($this, zone_with_code($this->org, REAL_SUBACCOUNT))->assertDispatched('open-paystack');

    initialized_with(null);
    Log::shouldHaveReceived('error')
        ->withArgs(
            static fn(string $message, array $context): bool => (
                str_contains($message, 'does not recognise it')
                && $context['paystack_mode'] === 'test'
                && $context['subaccount'] === REAL_SUBACCOUNT
                && $context['http_status'] === 404
            ),
        )
        ->once();
});

test('malformed or placeholder codes are never sent to paystack', function (): void {
    Log::spy();

    checkout_with_zone($this, zone_with_code($this->org, 'ACCT_accra_001'))->assertDispatched('open-paystack');

    initialized_with(null);
    expect(subaccount_lookups())->toBe(0);
    Log::shouldHaveReceived('warning')
        ->withArgs(static fn(string $message): bool => str_contains($message, 'not a valid'))
        ->once();
});

test('a subaccount lookup that cannot reach paystack falls back to an unsplit checkout', function (): void {
    Http::fake(['api.paystack.co/subaccount/*' => static fn() => throw new ConnectionException('timed out')]);
    Log::spy();

    checkout_with_zone($this, zone_with_code($this->org, REAL_SUBACCOUNT))->assertDispatched('open-paystack');

    initialized_with(null);
    Log::shouldHaveReceived('error')
        ->withArgs(static fn(string $message): bool => str_contains($message, 'request failed'))
        ->once();
});

test('split payments switched off skip subaccounts without calling paystack', function (): void {
    config(['services.paystack.split_payments' => false]);

    checkout_with_zone($this, zone_with_code($this->org, REAL_SUBACCOUNT))->assertDispatched('open-paystack');

    initialized_with(null);
    expect(subaccount_lookups())->toBe(0);
});

test('an organization can opt out of split payments in its settings', function (): void {
    $this->org->update(['settings' => ['paystack_split_payments' => false]]);

    checkout_with_zone($this, zone_with_code($this->org, REAL_SUBACCOUNT))->assertDispatched('open-paystack');

    initialized_with(null);
    expect(subaccount_lookups())->toBe(0);
});

test('verification is cached per api key so switching test and live keys re-verifies', function (): void {
    Http::fake(['api.paystack.co/subaccount/*' => Http::response(['status' => true])]);
    $zone = zone_with_code($this->org, REAL_SUBACCOUNT);
    $resolver = app(PaystackSubaccountResolver::class);

    expect($resolver->forSubdivision($zone))
        ->toBe(REAL_SUBACCOUNT)
        ->and($resolver->forSubdivision($zone))
        ->toBe(REAL_SUBACCOUNT);
    expect(subaccount_lookups())->toBe(1);

    config(['services.paystack.secret_key' => 'sk_live_xyz789']);

    expect($resolver->forSubdivision($zone))->toBe(REAL_SUBACCOUNT);
    expect(subaccount_lookups())->toBe(2);
});

test('without a secret key subaccounts cannot be verified and are omitted', function (): void {
    config(['services.paystack.secret_key' => '']);

    expect(app(PaystackSubaccountResolver::class)->forSubdivision(zone_with_code(
        $this->org,
        REAL_SUBACCOUNT,
    )))->toBeNull();

    expect(subaccount_lookups())->toBe(0);
});

test('the order records which subaccount its revenue was routed to', function (): void {
    Http::fake(['api.paystack.co/subaccount/*' => Http::response(['status' => true])]);
    $zone = zone_with_code($this->org, REAL_SUBACCOUNT);

    checkout_with_zone($this, $zone);

    expect(Order::query()->sole()->payout_subaccount_code)->toBe(REAL_SUBACCOUNT);
});
