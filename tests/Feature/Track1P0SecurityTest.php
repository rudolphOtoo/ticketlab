<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->organization = Organization::factory()->create([
        'name' => 'Grace Chapel',
        'slug' => 'grace-chapel',
        'subdivision_label' => 'Zone',
    ]);

    $this->zone = Subdivision::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Zone A · Kumasi',
        'slug' => 'zone-a-kumasi',
    ]);

    $this->event = Event::factory()->create([
        'organization_id' => $this->organization->id,
        'title' => 'Kingdom Summit 2026',
        'slug' => 'kingdom-summit-2026',
        'status' => 'published',
    ]);

    $this->ticket = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Regular Pass',
        'price' => 100.00,
        'total_capacity' => 10,
        'available_quantity' => 10,
    ]);

    $this->createPendingOrder = function (int $quantity = 2): array {
        $reservation = app(TicketReservationService::class)->hold(
            $this->ticket,
            $quantity,
            (string) Str::uuid(),
            $this->zone->id,
        );

        $breakdown = PaystackFeeCalculator::breakdown((float) $this->ticket->price * $quantity);

        $order = Order::factory()->create([
            'organization_id' => $this->organization->id,
            'subdivision_id' => $this->zone->id,
            'reservation_id' => $reservation->id,
            'reference' => 'TKT-P0-' . strtoupper((string) Str::ulid()),
            'customer_name' => 'Ama Mensah',
            'customer_email' => 'ama@example.com',
            'customer_phone' => '0244000000',
            'gross_amount' => $breakdown['gross'],
            'net_amount' => $breakdown['net'],
            'fee_amount' => $breakdown['processing_fee'],
            'currency' => 'GHS',
            'payment_status' => OrderStatus::Pending,
            'paid_at' => null,
            'metadata' => [
                'reservation_id' => $reservation->id,
                'ticket_type_id' => $this->ticket->id,
                'quantity' => $quantity,
            ],
        ]);

        return [$reservation, $order];
    };

    $this->postSignedWebhook = function (
        Order $order,
        int $amount,
        string $currency = 'GHS',
        #[SensitiveParameter]
        ?string $signingSecret = null,
    ) {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $order->reference,
                'status' => 'success',
                'channel' => 'mobile_money',
                'gateway_response' => 'Successful',
                'amount' => $amount,
                'currency' => $currency,
            ],
        ];

        $secret = $signingSecret ?? (string) config('services.paystack.secret_key');
        $signature = hash_hmac('sha512', (string) json_encode($payload), $secret);

        return $this->postJson(route('public.paystack.webhook'), $payload, [
            'x-paystack-signature' => $signature,
        ]);
    };
});

test('a late payment cannot fulfil a reservation that has already expired', function () {
    config(['services.paystack.secret_key' => 'sk_test_p0']);

    [$reservation, $order] = ($this->createPendingOrder)(2);

    $reservation->update(['expires_at' => now()->subMinute()]);

    Artisan::call('reservations:release-expired');

    expect($this->ticket->fresh()->available_quantity)->toBe(10);

    ($this->postSignedWebhook)($order, 20398)->assertStatus(409)->assertJson(['status' => 'requires_review']);

    $order->refresh();

    expect($order->payment_status)
        ->toBe(OrderStatus::RequiresReview)
        ->and($order->paid_at)
        ->toBeNull()
        ->and(Attendee::query()->where('order_id', $order->id)->count())
        ->toBe(0)
        ->and($reservation->fresh()->status)
        ->toBe(ReservationStatus::Expired)
        ->and($this->ticket->fresh()->available_quantity)
        ->toBe(10);
});

test('a payment that does not match the order total or currency is rejected', function (int $amount, string $currency) {
    config(['services.paystack.secret_key' => 'sk_test_p0']);

    [$reservation, $order] = ($this->createPendingOrder)(2);

    ($this->postSignedWebhook)($order, $amount, $currency)
        ->assertStatus(409)
        ->assertJson(['status' => 'requires_review']);

    $order->refresh();

    expect($order->payment_status)
        ->toBe(OrderStatus::RequiresReview)
        ->and($order->paid_at)
        ->toBeNull()
        ->and($order->metadata['gateway_amount_pesewas'])
        ->toBe($amount)
        ->and($order->metadata['gateway_currency'])
        ->toBe($currency)
        ->and($order->metadata['payment_channel'])
        ->toBe('mobile_money')
        ->and($order->metadata['requires_manual_review'])
        ->toBeTrue()
        ->and(Attendee::query()->where('order_id', $order->id)->count())
        ->toBe(0)
        ->and($reservation->fresh()->status)
        ->toBe(ReservationStatus::Active)
        ->and($this->ticket->fresh()->available_quantity)
        ->toBe(8);
})->with([
    'underpayment' => [1, 'GHS'],
    'wrong currency' => [20398, 'NGN'],
]);

test('webhook verification fails closed when the paystack secret is blank', function () {
    config([
        'services.paystack.secret_key' => '',
        'payments.providers.paystack.secret_key' => 'sk_test_legacy_secret',
    ]);

    [$reservation, $order] = ($this->createPendingOrder)(2);

    ($this->postSignedWebhook)($order, 20398, 'GHS', 'sk_test_legacy_secret')->assertUnauthorized();

    expect($order->fresh()->payment_status)
        ->toBe(OrderStatus::Pending)
        ->and(Attendee::query()->where('order_id', $order->id)->count())
        ->toBe(0)
        ->and($reservation->fresh()->status)
        ->toBe(ReservationStatus::Active)
        ->and($this->ticket->fresh()->available_quantity)
        ->toBe(8);
});

test('retrying checkout reuses the session reservation and pending order', function () {
    $component = Livewire::test(EventShow::class, [
        'org_slug' => $this->organization->slug,
        'event_slug' => $this->event->slug,
    ])
        ->set('quantity', 2)
        ->set('subdivisionId', $this->zone->id)
        ->set('customerName', 'Ama Mensah')
        ->set('customerEmail', 'ama@example.com')
        ->set('customerPhone', '0244000000')
        ->call('startCheckout')
        ->assertHasNoErrors()
        ->assertDispatched('open-paystack');

    $reservation = Reservation::query()->sole();
    $order = Order::query()->sole();
    $reference = $order->reference;
    $expiresAt = $reservation->expires_at->getTimestamp();

    $component
        ->call('startCheckout')
        ->assertHasNoErrors()
        ->assertDispatched('open-paystack')
        ->assertSet('orderReference', $reference);

    expect(Reservation::query()->count())
        ->toBe(1)
        ->and(Order::query()->count())
        ->toBe(1)
        ->and($reservation->fresh()->status)
        ->toBe(ReservationStatus::Active)
        ->and($reservation->fresh()->expires_at->getTimestamp())
        ->toBe($expiresAt)
        ->and($order->fresh()->payment_status)
        ->toBe(OrderStatus::Pending)
        ->and($order->fresh()->reservation_id)
        ->toBe($reservation->id)
        ->and($this->ticket->fresh()->available_quantity)
        ->toBe(8);
});

test('public event routes cannot shadow operations admin or api routes', function () {
    Route::middleware('web')->get('ops/p0-probe', fn(): string => 'ops route');
    Route::middleware('web')->get('admin/p0-probe', fn(): string => 'admin route');
    Route::middleware('web')->get('api/p0-probe', fn(): string => 'api route');

    $this->get('/ops/p0-probe')->assertOk()->assertSee('ops route');
    $this->get('/admin/p0-probe')->assertOk()->assertSee('admin route');
    $this->get('/api/p0-probe')->assertOk()->assertSee('api route');
});
