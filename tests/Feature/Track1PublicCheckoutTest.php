<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Livewire\Public\EventShow;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Services\PaystackFeeCalculator;
use App\Services\Reservation\TicketReservationService;
use App\Services\Reservation\TicketSoldOutException;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'name' => 'Grace Chapel',
        'slug' => 'grace-chapel',
        'subdivision_label' => 'Zone',
    ]);

    $this->zoneA = Subdivision::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Zone A · Kumasi',
        'slug' => 'zone-a-kumasi',
        'paystack_subaccount_code' => 'ACCT_ZONE_A',
    ]);

    $this->zoneB = Subdivision::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Zone B · Accra',
        'slug' => 'zone-b-accra',
        'paystack_subaccount_code' => 'ACCT_ZONE_B',
    ]);

    $this->event = Event::factory()->create([
        'organization_id' => $this->organization->id,
        'title' => 'Kingdom Summit 2026',
        'slug' => 'kingdom-summit-2026',
        'status' => 'published',
    ]);

    $this->regularTicket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Regular Pass',
        'price' => 100.00,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    $this->vipTicket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'VIP Gold Pass',
        'price' => 200.00,
        'total_capacity' => 20,
        'available_quantity' => 20,
    ]);
});

/*
 |--------------------------------------------------------------------------
 | 1. Atomic reservation hold / overselling prevention
 |--------------------------------------------------------------------------
 */

test('atomic ticket holds prevent overselling when capacity is exhausted', function () {
    $ticket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Limited Pass',
        'price' => 75.00,
        'total_capacity' => 5,
        'available_quantity' => 5,
    ]);

    $reservations = app(TicketReservationService::class);

    $firstHold = $reservations->hold($ticket, 5, 'session-first', $this->zoneA->id);

    expect($firstHold->status)
        ->toBe(ReservationStatus::Active)
        ->and($firstHold->quantity)
        ->toBe(5)
        ->and($firstHold->expires_at->greaterThan(now()->addMinutes(14)))
        ->toBeTrue()
        ->and($ticket->fresh()->available_quantity)
        ->toBe(0);

    expect(fn() => $reservations->hold($ticket->fresh(), 1, 'session-second', $this->zoneA->id))
        ->toThrow(TicketSoldOutException::class);

    expect($ticket->fresh()->available_quantity)->toBe(0)->and(Reservation::query()->count())->toBe(1);
});

test('a partial hold cannot push stock below zero', function () {
    $ticket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'total_capacity' => 3,
        'available_quantity' => 3,
    ]);

    $reservations = app(TicketReservationService::class);

    $reservations->hold($ticket, 2, 'session-one', null);

    expect(fn() => $reservations->hold($ticket->fresh(), 2, 'session-two', null))
        ->toThrow(TicketSoldOutException::class);

    expect($ticket->fresh()->available_quantity)->toBe(1);
});

/*
 |--------------------------------------------------------------------------
 | 2. Expired reservation reclamation
 |--------------------------------------------------------------------------
 */

test('the release expired command restores stock and expires only lapsed holds', function () {
    $ticket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'total_capacity' => 10,
        'available_quantity' => 10,
    ]);

    $reservations = app(TicketReservationService::class);

    $expiredHold = $reservations->hold($ticket, 3, 'session-expired', $this->zoneA->id);
    $activeHold = $reservations->hold($ticket->fresh(), 2, 'session-active', $this->zoneA->id);

    $expiredHold->update(['expires_at' => now()->subMinute()]);

    expect($ticket->fresh()->available_quantity)->toBe(5);

    Artisan::call('reservations:release-expired');

    expect($ticket->fresh()->available_quantity)
        ->toBe(8)
        ->and($expiredHold->fresh()->status)
        ->toBe(ReservationStatus::Expired)
        ->and($activeHold->fresh()->status)
        ->toBe(ReservationStatus::Active);

    // Running the command again must not double-credit reclaimed stock.
    Artisan::call('reservations:release-expired');

    expect($ticket->fresh()->available_quantity)->toBe(8);
});

/*
 |--------------------------------------------------------------------------
 | 3. Paystack GH surcharge precision
 |--------------------------------------------------------------------------
 */

test('the surcharge engine computes exact zero-net-loss gross values in pesewas', function () {
    $standard = PaystackFeeCalculator::breakdown(100.00);

    expect(PaystackFeeCalculator::grossPesewas(100.00))
        ->toBe(10199)
        ->and(PaystackFeeCalculator::gross(100.00))
        ->toBe(101.99)
        ->and($standard['net'])
        ->toBe(100.00)
        ->and($standard['processing_fee'])
        ->toBe(1.99)
        ->and($standard['gross_pesewas'])
        ->toBe(10199);

    $withPlatformFee = PaystackFeeCalculator::breakdown(100.00, 3.00);

    expect(PaystackFeeCalculator::grossPesewas(100.00, 3.00))
        ->toBe(10505)
        ->and($withPlatformFee['gross'])
        ->toBe(105.05)
        ->and($withPlatformFee['processing_fee'])
        ->toBe(2.05)
        ->and($withPlatformFee['platform_fee'])
        ->toBe(3.00);

    expect(PaystackFeeCalculator::grossPesewas(50.00, 3.00))->toBe(5406);
});

/*
 |--------------------------------------------------------------------------
 | 4. Public event landing & checkout drawer
 |--------------------------------------------------------------------------
 */

test('the public event landing renders branding, tiers, badges and subdivisions', function () {
    $this
        ->get(route('public.event.show', [
            'org_slug' => $this->organization->slug,
            'event_slug' => $this->event->slug,
        ]))
        ->assertOk()
        ->assertSee('Grace Chapel')
        ->assertSee('Kingdom Summit 2026')
        ->assertSee('Regular Pass')
        ->assertSee('VIP Gold Pass')
        ->assertSee('Available')
        ->assertSee('Zone A · Kumasi')
        ->assertSee('Zone B · Accra')
        ->assertSee('GHS 100.00');
});

test('the checkout flow holds stock and dispatches the paystack payload', function () {
    $initialQuantity = $this->regularTicket->available_quantity;

    Livewire::test(EventShow::class, [
        'org_slug' => $this->organization->slug,
        'event_slug' => $this->event->slug,
    ])
        ->assertSet('ticketTypeId', $this->regularTicket->id)
        ->call('selectTicketType', $this->regularTicket->id)
        ->set('quantity', 2)
        ->set('subdivisionId', $this->zoneA->id)
        ->set('customerName', 'Ama Mensah')
        ->set('customerEmail', 'ama@example.com')
        ->set('customerPhone', '0244000000')
        ->call('startCheckout')
        ->assertHasNoErrors()
        ->assertDispatched('open-paystack');

    $reservation = Reservation::query()->latest('id')->first();
    $order = Order::query()->latest('id')->first();

    expect($reservation)
        ->not->toBeNull()->and($reservation->quantity)->toBe(2)->and($reservation->status)->toBe(
            ReservationStatus::Active,
        )->and($reservation->subdivision_id)->toBe($this->zoneA->id)->and($order)
        ->not->toBeNull()->and($order->payment_status)->toBe(
            OrderStatus::Pending,
        )->and((float) $order->net_amount)->toBe(200.00)->and((float) $order->gross_amount)->toBe(
            203.98,
        )->and((float) $order->fee_amount)->toBe(3.98)->and(
            $order->metadata['reservation_id'],
        )->toBe($reservation->id)->and($this->regularTicket->fresh()->available_quantity)->toBe($initialQuantity - 2);
});

/*
 |--------------------------------------------------------------------------
 | 5. PayZephyr webhook verification & QR issuance
 |--------------------------------------------------------------------------
 */

test('a signed charge success webhook fulfils a pending order and issues sha256 qr passes', function () {
    config(['services.paystack.secret_key' => 'sk_test_webhook_secret']);
    config(['services.paystack.public_key' => 'pk_test_public']);

    $ticket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Summit Pass',
        'price' => 100.00,
        'total_capacity' => 10,
        'available_quantity' => 10,
    ]);

    $reservation = app(TicketReservationService::class)->hold($ticket, 2, 'session-webhook', $this->zoneA->id);

    $order = Order::factory()->create([
        'organization_id' => $this->organization->id,
        'subdivision_id' => $this->zoneA->id,
        'reservation_id' => $reservation->id,
        'reference' => 'TKT-WEBHOOK-001',
        'customer_name' => 'Kofi Boateng',
        'customer_email' => 'kofi@example.com',
        'customer_phone' => '0244111222',
        'gross_amount' => 203.98,
        'net_amount' => 200.00,
        'fee_amount' => 3.98,
        'currency' => 'GHS',
        'payment_status' => OrderStatus::Pending,
        'paid_at' => null,
        'metadata' => [
            'reservation_id' => $reservation->id,
            'ticket_type_id' => $ticket->id,
            'quantity' => 2,
        ],
    ]);

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'reference' => $order->reference,
            'status' => 'success',
            'channel' => 'mobile_money',
            'gateway_response' => 'Successful',
            'amount' => 20398,
            'currency' => 'GHS',
        ],
    ];

    $signature = hash_hmac('sha512', json_encode($payload), 'sk_test_webhook_secret');

    $this->postJson(route('public.paystack.webhook'), $payload, [
        'x-paystack-signature' => $signature,
    ])->assertOk()->assertJson(['status' => 'success']);

    $order->refresh();
    $attendees = Attendee::query()->where('order_id', $order->id)->get();

    expect($order->payment_status)
        ->toBe(OrderStatus::Paid)
        ->and($order->paid_at)
        ->not
        ->toBeNull()
        ->and($order->payment_method)
        ->toBe(PaymentMethod::MobileMoney)
        ->and($reservation->fresh()->status)
        ->toBe(ReservationStatus::Fulfilled)
        ->and($attendees)
        ->toHaveCount(2)
        ->and($attendees->pluck('qr_code_hash')->unique())
        ->toHaveCount(2)
        ->and($ticket->fresh()->available_quantity)
        ->toBe(8);

    foreach ($attendees as $attendee) {
        expect($attendee->qr_code_hash)
            ->toMatch('/^[a-f0-9]{64}$/')
            ->and($attendee->organization_id)
            ->toBe($this->organization->id)
            ->and($attendee->subdivision_id)
            ->toBe($this->zoneA->id)
            ->and($attendee->ticket_type_id)
            ->toBe($ticket->id);
    }

    // Replayed webhooks must be idempotent.
    $this->postJson(route('public.paystack.webhook'), $payload, [
        'x-paystack-signature' => $signature,
    ])->assertOk();

    expect(Attendee::query()->where('order_id', $order->id)->count())->toBe(2);
});

test('an invalid webhook signature is rejected without fulfilling the order', function () {
    config(['services.paystack.secret_key' => 'sk_test_webhook_secret']);

    $order = Order::factory()->create([
        'organization_id' => $this->organization->id,
        'subdivision_id' => $this->zoneA->id,
        'payment_status' => OrderStatus::Pending,
        'metadata' => [
            'ticket_type_id' => $this->regularTicket->id,
            'quantity' => 1,
        ],
    ]);

    $this->postJson(
        route('public.paystack.webhook'),
        [
            'event' => 'charge.success',
            'data' => [
                'reference' => $order->reference,
                'status' => 'success',
            ],
        ],
        [
            'x-paystack-signature' => 'not-a-valid-signature',
        ],
    )->assertUnauthorized();

    expect($order->fresh()->payment_status)->toBe(OrderStatus::Pending);
});

/*
 |--------------------------------------------------------------------------
 | 6. QR ticket confirmation view
 |--------------------------------------------------------------------------
 */

test('the ticket pass page renders attendee details, zone and inline svg qr codes', function () {
    $order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $this->organization->id,
            'subdivision_id' => $this->zoneA->id,
            'reference' => 'TKT-PASS-001',
            'customer_name' => 'Adwoa Owusu',
            'customer_email' => 'adwoa@example.com',
            'customer_phone' => '0244333444',
            'paid_at' => now(),
            'metadata' => [],
        ]);

    $attendee = Attendee::factory()->create([
        'order_id' => $order->id,
        'organization_id' => $this->organization->id,
        'subdivision_id' => $this->zoneA->id,
        'ticket_type_id' => $this->vipTicket->id,
        'name' => 'Adwoa Owusu',
        'email' => 'adwoa@example.com',
        'phone' => '0244333444',
        'qr_code_hash' => hash('sha256', 'track-one-pass'),
    ]);

    $this
        ->get(route('public.tickets.show', ['order' => $order->reference]))
        ->assertOk()
        ->assertSee('Adwoa Owusu')
        ->assertSee('VIP Gold Pass')
        ->assertSee('Zone A · Kumasi')
        ->assertSee($order->reference)
        ->assertSee($attendee->qr_code_hash)
        ->assertSee('<svg', false);
});

test('the ticket pass page shows a pending state before payment confirmation', function () {
    $order = Order::factory()->create([
        'organization_id' => $this->organization->id,
        'subdivision_id' => $this->zoneA->id,
        'reference' => 'TKT-PENDING-001',
        'payment_status' => OrderStatus::Pending,
        'paid_at' => null,
        'metadata' => [],
    ]);

    $this
        ->get(route('public.tickets.show', ['order' => $order->reference]))
        ->assertOk()
        ->assertSee('Awaiting payment')
        ->assertSee('TKT-PENDING-001');
});
