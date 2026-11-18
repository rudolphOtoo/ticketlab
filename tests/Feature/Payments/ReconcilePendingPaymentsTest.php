<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Livewire\Public\EventShow;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Services\Payment\PaystackGateway;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->org = Organization::factory()->create(['slug' => 'gmsu']);
    $this->zone = payable_zone($this->org, 'Kumasi');
    $event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'slug' => 'convention',
        'status' => 'published',
    ]);
    $this->tier = TicketType::factory()->create([
        'event_id' => $event->id,
        'price' => 100,
        'total_capacity' => 10,
        'available_quantity' => 10,
    ]);

    // A buyer opened Paystack (2 seats held), then closed the browser: no webhook ever arrived.
    Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention'])
        ->set('ticketTypeId', $this->tier->id)
        ->set('subdivisionId', $this->zone->id)
        ->set('quantity', 2)
        ->set('customerName', 'Ama Mensah')
        ->set('customerEmail', 'ama@example.com')
        ->set('customerPhone', '0244000000')
        ->call('startCheckout')
        ->assertHasNoErrors();

    $this->order = Order::query()->sole();
    expect($this->tier->fresh()->available_quantity)->toBe(8);
});

/**
 * Paystack's verify answer for the order, with the given status (and overrides).
 *
 * @param array<string, mixed> $overrides
 */
function paystack_verifies(Order $order, string $status, array $overrides = [], ?Closure $before = null): void
{
    Http::fake(['api.paystack.co/transaction/verify/*' => static function () use (
        $order,
        $status,
        $overrides,
        $before,
    ) {
        $before?->__invoke();

        return Http::response([
            'status' => true,
            'data' => [
                'reference' => $order->gatewayReference(),
                'status' => $status,
                'amount' => $order->gross_pesewas,
                'currency' => 'GHS',
                'channel' => 'mobile_money',
                ...paystack_routing($order),
                ...$overrides,
            ],
        ]);
    }]);
}

function verify_calls(): int
{
    return Http::recorded(static fn(Request $request): bool => str_contains(
        $request->url(),
        '/transaction/verify/',
    ))->count();
}

test('it runs every 10 minutes on the scheduler, executing', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(static fn($event): bool => str_contains((string) $event->command, 'payments:reconcile-pending'));

    expect($event)
        ->not
        ->toBeNull()
        ->and($event->expression)
        ->toBe('*/10 * * * *')
        ->and((string) $event->command)
        ->toContain('--execute');
});

test('orders newer than --older-than are not touched and Paystack is not asked', function (): void {
    $this->travel(5)->minutes();
    paystack_verifies($this->order, 'success');

    $this->artisan('payments:reconcile-pending', ['--older-than' => 10, '--execute' => true])->assertSuccessful();

    expect(verify_calls())->toBe(0)->and($this->order->fresh()->payment_status)->toBe(OrderStatus::Pending);
});

test('the dry run reports what it would do and changes nothing', function (): void {
    $this->travel(11)->minutes();
    paystack_verifies($this->order, 'success');

    $this
        ->artisan('payments:reconcile-pending')
        ->expectsOutputToContain('confirm and issue passes')
        ->expectsOutputToContain('Dry run: nothing was changed')
        ->assertSuccessful();

    expect($this->order->fresh()->payment_status)
        ->toBe(OrderStatus::Pending)
        ->and($this->order->attendees()->count())
        ->toBe(0);
});

test('a payment Paystack confirms is settled exactly like the webhook would, once', function (): void {
    $this->travel(11)->minutes();
    paystack_verifies($this->order, 'success');

    $this
        ->artisan('payments:reconcile-pending', ['--execute' => true])
        ->expectsOutputToContain('confirmed (2 pass(es))')
        ->assertSuccessful();
    $this->artisan('payments:reconcile-pending', ['--execute' => true])->assertSuccessful();

    expect($this->order->fresh()->payment_status)
        ->toBe(OrderStatus::Paid)
        ->and($this->order->attendees()->count())
        ->toBe(2)
        ->and($this->order->attendees()->pluck('subdivision_id')->unique()->all())
        ->toBe([$this->zone->id])
        ->and($this->tier->fresh()->available_quantity)
        ->toBe(8);
});

test('a confirmed payment with the wrong amount or payout account is held for review, not issued', function (array $overrides): void {
    $this->travel(11)->minutes();
    paystack_verifies($this->order, 'success', $overrides);

    $this
        ->artisan('payments:reconcile-pending', ['--execute' => true])
        ->expectsOutputToContain('held for review')
        ->assertSuccessful();

    expect($this->order->fresh()->payment_status)
        ->toBe(OrderStatus::RequiresReview)
        ->and($this->order->attendees()->count())
        ->toBe(0);
})->with([
    'short paid' => [['amount' => 100]],
    'other subaccount' => [['subaccount' => ['subaccount_code' => 'ACCT_someoneelse1']]],
]);

test('failed and abandoned payments close the order and release its seats', function (
    string $paystackStatus,
    OrderStatus $expected,
): void {
    $this->travel(11)->minutes();
    paystack_verifies($this->order, $paystackStatus);

    $this
        ->artisan('payments:reconcile-pending', ['--execute' => true])
        ->expectsOutputToContain('2 seat(s) released')
        ->assertSuccessful();

    expect($this->order->fresh()->payment_status)
        ->toBe($expected)
        ->and($this->order->fresh()->reservation->status)
        ->toBe(ReservationStatus::Released)
        ->and($this->tier->fresh()->available_quantity)
        ->toBe(10);
})->with([
    'failed' => ['failed', OrderStatus::Failed],
    'abandoned' => ['abandoned', OrderStatus::Cancelled],
]);

test('an order Paystack never saw is kept while its seat hold is live, then closed', function (): void {
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
        'status' => false,
        'message' => 'Transaction reference not found',
    ], 404)]);

    $this->travel(11)->minutes();
    $this
        ->artisan('payments:reconcile-pending', ['--execute' => true])
        ->expectsOutputToContain('seat hold still live')
        ->assertSuccessful();
    expect($this->order->fresh()->payment_status)->toBe(OrderStatus::Pending);

    $this->travel(10)->minutes();
    $this->artisan('payments:reconcile-pending', ['--execute' => true])->assertSuccessful();
    expect($this->order->fresh()->payment_status)
        ->toBe(OrderStatus::Cancelled)
        ->and($this->tier->fresh()->available_quantity)
        ->toBe(10);
});

test('an unreachable Paystack leaves the order for the next run', function (): void {
    Http::fake(['api.paystack.co/transaction/verify/*' => static fn() => throw new ConnectionException('timed out')]);
    $this->travel(11)->minutes();

    $this
        ->artisan('payments:reconcile-pending', ['--execute' => true])
        ->expectsOutputToContain('try next run')
        ->assertSuccessful();

    expect($this->order->fresh()->payment_status)
        ->toBe(OrderStatus::Pending)
        ->and($this->tier->fresh()->available_quantity)
        ->toBe(8);
});

test('a webhook that lands while reconcile is confirming the same order issues the passes only once', function (): void {
    $this->travel(11)->minutes();
    $order = $this->order;

    // Paystack's webhook arrives between reconcile's read and its write.
    paystack_verifies($order, 'success', [], before: static function () use ($order): void {
        app(PaystackGateway::class)->handleChargeSuccess([
            'reference' => $order->gatewayReference(),
            'status' => 'success',
            'amount' => $order->gross_pesewas,
            'currency' => 'GHS',
            ...paystack_routing($order),
        ]);
    });

    $this->artisan('payments:reconcile-pending', ['--execute' => true])->assertSuccessful();

    expect($order->fresh()->payment_status)->toBe(OrderStatus::Paid)->and($order->attendees()->count())->toBe(2);
});

test('a webhook that confirms payment while reconcile is releasing the order wins, and no seats are released', function (): void {
    $this->travel(11)->minutes();
    $order = $this->order;

    // Paystack still said "abandoned" when asked, but the buyer's retry was confirmed by the webhook just after.
    paystack_verifies($order, 'abandoned', [], before: static function () use ($order): void {
        app(PaystackGateway::class)->handleChargeSuccess([
            'reference' => $order->gatewayReference(),
            'status' => 'success',
            'amount' => $order->gross_pesewas,
            'currency' => 'GHS',
            ...paystack_routing($order),
        ]);
    });

    $this
        ->artisan('payments:reconcile-pending', ['--execute' => true])
        ->expectsOutputToContain('already settled elsewhere')
        ->assertSuccessful();

    expect($order->fresh()->payment_status)
        ->toBe(OrderStatus::Paid)
        ->and($order->attendees()->count())
        ->toBe(2)
        ->and($this->tier->fresh()->available_quantity)
        ->toBe(8);
});
