<?php

declare(strict_types=1);

use App\Livewire\Admin\EventManager;
use App\Livewire\Admin\TicketManager;
use App\Livewire\Ops\CashDeskTerminal;
use App\Livewire\Public\EventShow;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\TicketTypeZoneCapacity;
use App\Models\User;
use App\Services\PaystackFeeCalculator;
use App\Services\Reservation\TicketReservationService;
use App\Services\Reservation\TicketSoldOutException;
use App\Support\ActiveEvent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->org = Organization::factory()->create(['slug' => 'gmsu', 'subdivision_label' => 'Zone']);
    $this->accra = payable_zone($this->org, 'Accra');
    $this->kumasi = payable_zone($this->org, 'Kumasi');
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'slug' => 'convention',
        'status' => 'published',
    ]);
    $this->vip = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'VIP',
        'price' => 100,
        'total_capacity' => 50,
        'available_quantity' => 50,
    ]);
    $this->orgAdmin = User::factory()->create()->assignMembership('admin', $this->org->id);
});

function buy_in_zone(object $test, ?Subdivision $zone, int $quantity = 1): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention'])
        ->set('ticketTypeId', $test->vip->id)
        ->set('subdivisionId', $zone?->id)
        ->set('quantity', $quantity)
        ->set('customerName', 'Ama Mensah')
        ->set('customerEmail', 'ama@example.com')
        ->set('customerPhone', '0244000000')
        ->checkoutAsShown();
}

test('a Kumasi purchase is split to the Kumasi subaccount, which receives exactly the ticket value', function (): void {
    buy_in_zone($this, $this->kumasi)->assertHasNoErrors()->assertDispatched('open-paystack');

    $order = Order::query()->sole();
    expect($order->subdivision_id)
        ->toBe($this->kumasi->id)
        ->and($order->payout_subaccount_code)
        ->toBe($this->kumasi->paystack_subaccount_code);

    Http::assertSent(
        fn(Request $request): bool => (
            str_ends_with($request->url(), '/transaction/initialize')
            && $request['subaccount'] === $this->kumasi->paystack_subaccount_code
            && $request['bearer'] === 'account'
            // The main account keeps only the processing fee; Kumasi gets the full GHS 100.
            && ($request['amount'] - $request['transaction_charge']) === PaystackFeeCalculator::toPesewas(100)
        ),
    );
});

test('the webhook refuses a Kumasi order whose money settled into the Accra subaccount', function (): void {
    buy_in_zone($this, $this->kumasi);
    $order = Order::query()->sole();
    $payment = [
        'reference' => $order->gatewayReference(),
        'status' => 'success',
        'amount' => $order->gross_pesewas,
        'currency' => 'GHS',
        'subaccount' => ['subaccount_code' => $this->accra->paystack_subaccount_code],
    ];
    $body = (string) json_encode(['event' => 'charge.success', 'data' => $payment]);

    $this->call(
        'POST',
        '/api/paystack/webhook',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_suite'),
        ],
        $body,
    )->assertStatus(409);

    expect($order->fresh()->payment_status->value)->toBe('requires_review')->and($order->attendees()->count())->toBe(0);
});

test('checkout without a zone is rejected and the page offers no general admission option', function (): void {
    $this->get('/gmsu/convention')->assertOk()->assertSee('Choose your zone')->assertDontSee('general admission');

    buy_in_zone($this, null)->assertHasErrors(['subdivisionId' => 'required'])->assertNotDispatched('open-paystack');

    expect(Order::query()->count())->toBe(0)->and($this->vip->fresh()->available_quantity)->toBe(50);
});

test('a zone sells out on its own while other zones keep selling', function (): void {
    TicketTypeZoneCapacity::query()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->vip->id,
        'subdivision_id' => $this->kumasi->id,
        'capacity' => 2,
    ]);
    $holds = app(TicketReservationService::class);

    $holds->holdOrReuse($this->vip, 2, 'buyer-one', $this->kumasi->id);

    expect(fn() => $holds->holdOrReuse($this->vip, 1, 'buyer-two', $this->kumasi->id))
        ->toThrow(TicketSoldOutException::class, 'VIP is sold out in Kumasi.');

    // Accra has no cap of its own: only the tier total (48 left) applies.
    $holds->holdOrReuse($this->vip, 5, 'buyer-two', $this->accra->id);

    // A buyer resizing their own Kumasi hold is not blocked by it.
    $holds->holdOrReuse($this->vip, 1, 'buyer-one', $this->kumasi->id);
    $holds->holdOrReuse($this->vip, 2, 'buyer-one', $this->kumasi->id);

    expect($this->vip->fresh()->available_quantity)->toBe(43);

    // The page sends each zone's seats to the browser, which shows them as the buyer switches zone.
    Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention'])
        ->assertViewHas(
            'checkout',
            fn(array $checkout): bool => (
                $checkout['tiers'][0]['seats'][$this->kumasi->id] === 0
                && $checkout['tiers'][0]['seats'][$this->accra->id] === 43
            ),
        )
        ->set('subdivisionId', $this->kumasi->id)
        ->assertSee('Sold Out');
});

test('the cash desk checks the zone cap under the same lock', function (): void {
    TicketTypeZoneCapacity::query()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->vip->id,
        'subdivision_id' => $this->kumasi->id,
        'capacity' => 1,
    ]);
    $usher = User::factory()->create()->assignMembership('usher', $this->org->id, $this->kumasi->id);
    ActiveEvent::set($this->event);

    $desk = fn() => Livewire::actingAs($usher)
        ->test(CashDeskTerminal::class, ['eventId' => $this->event->id])
        ->call('selectTier', $this->vip->id)
        ->set('quantity', 1)
        ->set('cashTendered', '100.00');

    $desk()->call('processCashSale')->assertHasNoErrors()->assertDispatched('pass-issued');
    $desk()->call('processCashSale')->assertHasErrors('tier');

    expect(Order::query()->where('subdivision_id', $this->kumasi->id)->count())->toBe(1);
});

test('zone staff always sell for their own zone, whatever the request says', function (): void {
    $usher = User::factory()->create()->assignMembership('usher', $this->org->id, $this->kumasi->id);

    Livewire::actingAs($usher)
        ->test(CashDeskTerminal::class, ['eventId' => $this->event->id])
        ->assertSee('Zone:')
        ->assertDontSee('Choose zone…')
        ->set('saleZoneId', $this->accra->id)
        ->call('selectTier', $this->vip->id)
        ->set('cashTendered', '100.00')
        ->call('processCashSale')
        ->assertHasNoErrors();

    expect(Order::query()->sole()->subdivision_id)->toBe($this->kumasi->id);
});

test('an organisation admin must pick a zone at the cash desk', function (): void {
    $sale = Livewire::actingAs($this->orgAdmin)
        ->test(CashDeskTerminal::class, ['eventId' => $this->event->id])
        ->call('selectTier', $this->vip->id)
        ->set('cashTendered', '100.00');

    $sale->call('processCashSale')->assertHasErrors('zone');
    expect(Order::query()->count())->toBe(0);

    $sale->set('saleZoneId', Subdivision::factory()->create()->id)->call('processCashSale')->assertHasErrors('zone');

    $sale->set('saleZoneId', $this->accra->id)->call('processCashSale')->assertHasNoErrors();
    expect(Order::query()->sole()->subdivision_id)->toBe($this->accra->id);
});

test('a paid event cannot be published until every zone has a verified subaccount, and the admin is told which', function (): void {
    $this->event->update(['status' => 'draft']);
    Subdivision::factory()->create([
        'organization_id' => $this->org->id,
        'name' => 'Cape Coast',
        'paystack_subaccount_code' => null,
    ]);
    ActiveEvent::set($this->event);

    $manager = Livewire::actingAs($this->orgAdmin)->test(EventManager::class);

    $manager->call('togglePublished', $this->event->id)->assertSet(
        'flashMessage',
        'Cannot publish yet: Cape Coast has no verified Paystack payout account. Set one under Zone Payouts.',
    );
    expect($this->event->fresh()->status)->toBe('draft');

    $manager->call('editEvent', $this->event->id)->set('status', 'published')->call('save')->assertHasErrors('status');
    expect($this->event->fresh()->status)->toBe('draft');

    make_zones_payable($this->org);
    $manager->call('togglePublished', $this->event->id);
    expect($this->event->fresh()->status)->toBe('published');
});

test('a free-only event can be published without payout accounts', function (): void {
    $this->vip->update(['price' => 0]);
    $this->event->update(['status' => 'draft']);
    Subdivision::factory()->create(['organization_id' => $this->org->id, 'paystack_subaccount_code' => null]);

    Livewire::actingAs($this->orgAdmin)->test(EventManager::class)->call('togglePublished', $this->event->id);

    expect($this->event->fresh()->status)->toBe('published');
});

test('a paid tier cannot be opened on a published event while a zone cannot be paid', function (): void {
    Subdivision::factory()->create([
        'organization_id' => $this->org->id,
        'name' => 'Cape Coast',
        'paystack_subaccount_code' => null,
    ]);
    ActiveEvent::set($this->event);

    Livewire::actingAs($this->orgAdmin)
        ->test(TicketManager::class)
        ->call('openCreateModal')
        ->set('name', 'Delegate')
        ->set('price', '80.00')
        ->set('total_capacity', 100)
        ->call('save')
        ->assertHasErrors('price');

    expect(TicketType::query()->where('name', 'Delegate')->exists())->toBeFalse();
});

test('ticket types store a seat cap per zone and a blank field removes it', function (): void {
    ActiveEvent::set($this->event);

    $manager = Livewire::actingAs($this->orgAdmin)
        ->test(TicketManager::class)
        ->call('editTicketType', $this->vip->id)
        ->assertSee('Seats per zone')
        ->set("zoneCapacities.{$this->kumasi->id}", '20')
        ->set("zoneCapacities.{$this->accra->id}", '60')
        ->call('save')
        ->assertHasErrors("zoneCapacities.{$this->accra->id}");

    $manager->set("zoneCapacities.{$this->accra->id}", '30')->call('save')->assertHasNoErrors();
    expect(TicketTypeZoneCapacity::query()->pluck('capacity', 'subdivision_id')->sortKeys()->all())
        ->toBe(collect([$this->kumasi->id => 20, $this->accra->id => 30])->sortKeys()->all());

    $manager->call('editTicketType', $this->vip->id)->set("zoneCapacities.{$this->accra->id}", '')->call('save');
    expect(TicketTypeZoneCapacity::query()->pluck('subdivision_id')->all())->toBe([$this->kumasi->id]);
});

test('in the split payload the main account bears the Paystack fee and the zone receives exactly the ticket price', function (): void {
    $this->vip->update(['price' => 75.50]);

    buy_in_zone($this, $this->kumasi, 3)->assertHasNoErrors();

    $payload = collect(Http::recorded())
        ->map(static fn(array $pair) => $pair[0])
        ->first(static fn(Request $request): bool => str_ends_with($request->url(), '/transaction/initialize'));
    $ticketPrice = PaystackFeeCalculator::toPesewas(75.50 * 3);

    expect($payload['subaccount'])
        ->toBe($this->kumasi->paystack_subaccount_code)
        // "account" = the main (platform) account bears Paystack's fee, out of its transaction_charge.
        ->and($payload['bearer'])
        ->toBe('account')
        ->and($payload['amount'] - $payload['transaction_charge'])
        ->toBe($ticketPrice)
        ->and($payload['transaction_charge'])
        ->toBe($payload['amount'] - $ticketPrice);
});

test('an order created before zones were required still confirms and issues its pass when the webhook arrives late', function (?array $subaccountField): void {
    // In flight at deploy time: no zone, no payout subaccount, settling to the main account, seats still held.
    $reservation = App\Models\Reservation::factory()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->vip->id,
        'subdivision_id' => null,
        'quantity' => 1,
    ]);
    $order = Order::factory()->create([
        'organization_id' => $this->org->id,
        'subdivision_id' => null,
        'payout_subaccount_code' => null,
        'reservation_id' => $reservation->id,
        'gross_amount' => 102.0,
        'net_amount' => 100.0,
        'metadata' => ['reservation_id' => $reservation->id, 'ticket_type_id' => $this->vip->id, 'quantity' => 1],
    ]);

    $payment = [
        'reference' => $order->reference,
        'status' => 'success',
        'amount' => $order->gross_pesewas,
        'currency' => 'GHS',
        'channel' => 'mobile_money',
        ...($subaccountField === null ? [] : ['subaccount' => $subaccountField]),
    ];
    $body = (string) json_encode(['event' => 'charge.success', 'data' => $payment]);

    $this
        ->call(
            'POST',
            '/api/paystack/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_suite'),
            ],
            $body,
        )
        ->assertOk()
        ->assertJson(['status' => 'success']);

    expect($order->fresh()->payment_status->value)->toBe('paid')->and($order->fresh()->attendees()->count())->toBe(1);
})->with([
    'no subaccount field' => [null],
    // Paystack sends an empty subaccount object for unsplit transactions.
    'empty subaccount object' => [[]],
]);

/*
 |--------------------------------------------------------------------------
 | Ticket and zone choice happen in the browser; the server re-checks on Pay
 |--------------------------------------------------------------------------
 */

test('the page sends seats left per tier per zone, matching the checkout capacity check', function (): void {
    TicketTypeZoneCapacity::query()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->vip->id,
        'subdivision_id' => $this->kumasi->id,
        'capacity' => 3,
    ]);
    $cheap = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'price' => 50,
        'total_capacity' => 10,
        'available_quantity' => 10,
    ]);
    app(TicketReservationService::class)->holdOrReuse($this->vip, 2, 'someone-else', $this->kumasi->id);

    $capacity = app(App\Services\Reservation\ZoneCapacity::class);
    $tiers = collect([$this->vip->fresh(), $cheap]);
    $grid = $capacity->sellableGrid($tiers, [$this->accra->id, $this->kumasi->id]);

    foreach ($tiers as $tier) {
        expect($grid[$tier->id][0])->toBe($capacity->sellable($tier, null));

        foreach ([$this->accra, $this->kumasi] as $zone) {
            expect($grid[$tier->id][$zone->id])->toBe($capacity->sellable($tier, $zone->id));
        }
    }

    // Kumasi is capped at 3 with 2 held; Accra has no cap of its own, so only the tier's 48 left apply.
    expect($grid[$this->vip->id][$this->kumasi->id])->toBe(1)->and($grid[$this->vip->id][$this->accra->id])->toBe(48);
});

test('a tampered client total is refused before any seat is held or payment opened', function (int $offsetPesewas): void {
    $shown = PaystackFeeCalculator::grossPesewas(200.0);

    Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention'])
        ->set('ticketTypeId', $this->vip->id)
        ->set('quantity', 2)
        ->set('subdivisionId', $this->kumasi->id)
        ->set('customerName', 'Ama Mensah')
        ->set('customerEmail', 'ama@example.com')
        ->set('customerPhone', '0244000000')
        ->set('clientTotalPesewas', $shown + $offsetPesewas)
        ->call('startCheckout')
        ->assertHasErrors(['clientTotalPesewas' => EventShow::CHANGED_MESSAGE])
        ->assertDispatched('checkout-refreshed')
        ->assertNotDispatched('open-paystack');

    expect(Order::query()->count())
        ->toBe(0)
        ->and(App\Models\Reservation::query()->count())
        ->toBe(0)
        ->and($this->vip->fresh()->available_quantity)
        ->toBe(50);
    Http::assertNotSent(static fn(Request $request): bool => str_contains($request->url(), 'transaction/initialize'));
})->with(['one pesewa less' => [-1], 'free' => [-20_398], 'more' => [500]]);

test('a checkout without the shown total is refused', function (): void {
    Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention'])
        ->set('ticketTypeId', $this->vip->id)
        ->set('subdivisionId', $this->kumasi->id)
        ->set('customerName', 'Ama Mensah')
        ->set('customerEmail', 'ama@example.com')
        ->set('customerPhone', '0244000000')
        ->call('startCheckout')
        ->assertHasErrors(['clientTotalPesewas' => 'required']);

    expect(Order::query()->count())->toBe(0);
});

test('the event page never polls the server', function (): void {
    $this->get('/gmsu/convention')->assertOk()->assertDontSee('wire:poll', escape: false);
});

test('a price change after the page loaded refuses Pay and sends the new price for the buyer to review', function (): void {
    $page = Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention'])
        ->set('ticketTypeId', $this->vip->id)
        ->set('subdivisionId', $this->kumasi->id)
        ->set('customerName', 'Ama Mensah')
        ->set('customerEmail', 'ama@example.com')
        ->set('customerPhone', '0244000000')
        // What the page showed at the old price of GHS 100.
        ->set('clientTotalPesewas', PaystackFeeCalculator::grossPesewas(100.0));

    $this->vip->update(['price' => 120]);

    $page
        ->call('startCheckout')
        ->assertHasErrors(['clientTotalPesewas' => EventShow::CHANGED_MESSAGE])
        ->assertDispatched(
            'checkout-refreshed',
            static fn(string $name, array $params): bool => (
                collect($params['config']['tiers'])->firstWhere('name', 'VIP')['price'] === 12_000
            ),
        );

    expect(Order::query()->count())->toBe(0);

    // Paying again at the total now shown goes through.
    $page->checkoutAsShown()->assertHasNoErrors()->assertDispatched('open-paystack');
    expect((float) Order::query()->sole()->gross_amount)->toBe(122.39);
});

test('seats running out after the page loaded refuses Pay with the reason and fresh seat counts', function (): void {
    TicketTypeZoneCapacity::query()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->vip->id,
        'subdivision_id' => $this->kumasi->id,
        'capacity' => 3,
    ]);
    $page = Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention'])
        ->set('ticketTypeId', $this->vip->id)
        ->set('quantity', 3)
        ->set('subdivisionId', $this->kumasi->id)
        ->set('customerName', 'Ama Mensah')
        ->set('customerEmail', 'ama@example.com')
        ->set('customerPhone', '0244000000');

    app(TicketReservationService::class)->holdOrReuse($this->vip, 2, 'someone-else', $this->kumasi->id);

    $page
        ->checkoutAsShown()
        ->assertHasErrors('quantity')
        ->assertSee(EventShow::CHANGED_MESSAGE)
        ->assertDispatched(
            'checkout-refreshed',
            fn(string $name, array $params): bool => $params['config']['tiers'][0]['seats'][$this->kumasi->id] === 1,
        );
});
