<?php

declare(strict_types=1);

use App\Jobs\SendTicketSms;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentService;
use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\Drivers\LogSmsGateway;
use App\Services\Sms\Drivers\NullSmsGateway;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->org = Organization::factory()->create(['name' => 'Charis Ministries']);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'title' => 'Praise Summit']);
    $this->tier = TicketType::factory()->create(['event_id' => $this->event->id]);
});

test('sms module is disabled by default', function (): void {
    config()->set('sms.enabled', false);

    $sms = app(SmsGatewayInterface::class);

    expect($sms->isEnabled())->toBeFalse()
        ->and($sms->send('0244123456', 'Test SMS'))->toBeFalse();
});

test('null driver accepts dispatch silently when enabled', function (): void {
    config()->set('sms.enabled', true);
    config()->set('sms.default', 'null');

    $manager = app(SmsManager::class);

    expect($manager->driver())->toBeInstanceOf(NullSmsGateway::class)
        ->and($manager->send('0244123456', 'Hello null'))->toBeTrue();
});

test('log driver writes dispatch to laravel log', function (): void {
    config()->set('sms.enabled', true);
    config()->set('sms.default', 'log');

    Log::shouldReceive('channel')
        ->with('single')
        ->once()
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, '0244123456') && str_contains($message, 'Order #123');
        });

    $manager = app(SmsManager::class);
    expect($manager->driver())->toBeInstanceOf(LogSmsGateway::class);

    $sent = $manager->send('0244123456', 'Order #123 confirmed');
    expect($sent)->toBeTrue();
});

test('fulfilling an order does NOT queue SendTicketSms when sms is disabled', function (): void {
    config()->set('sms.enabled', false);
    Queue::fake();

    $this->event->update(['status' => 'published', 'slug' => 'summit-2027']);
    $this->tier->update(['price' => 50, 'total_capacity' => 10, 'available_quantity' => 10, 'is_active' => true]);

    $component = Livewire\Livewire::test(App\Livewire\Public\EventShow::class, [
        'org_slug' => $this->org->slug,
        'event_slug' => 'summit-2027',
    ])
        ->set('ticketTypeId', $this->tier->id)
        ->set('quantity', 1)
        ->set('customerName', 'Kofi Boateng')
        ->set('customerEmail', 'kofi@example.com')
        ->set('customerPhone', '0244998877')
        ->call('startCheckout')
        ->assertHasNoErrors();

    $order = Order::query()->where('reference', $component->get('orderReference'))->firstOrFail();
    $payment = [
        'reference' => $order->reference,
        'status' => 'success',
        'amount' => $order->gross_pesewas,
        'currency' => 'GHS',
        'channel' => 'mobile_money',
    ];

    app(OrderFulfillmentService::class)->fulfill($order, $payment);

    Queue::assertNotPushed(SendTicketSms::class);
});

test('fulfilling an order queues SendTicketSms when sms is enabled', function (): void {
    config()->set('sms.enabled', true);
    Queue::fake();

    $this->event->update(['status' => 'published', 'slug' => 'summit-2027']);
    $this->tier->update(['price' => 50, 'total_capacity' => 10, 'available_quantity' => 10, 'is_active' => true]);

    $component = Livewire\Livewire::test(App\Livewire\Public\EventShow::class, [
        'org_slug' => $this->org->slug,
        'event_slug' => 'summit-2027',
    ])
        ->set('ticketTypeId', $this->tier->id)
        ->set('quantity', 1)
        ->set('customerName', 'Kofi Boateng')
        ->set('customerEmail', 'kofi@example.com')
        ->set('customerPhone', '0244998877')
        ->call('startCheckout')
        ->assertHasNoErrors();

    $order = Order::query()->where('reference', $component->get('orderReference'))->firstOrFail();
    $payment = [
        'reference' => $order->reference,
        'status' => 'success',
        'amount' => $order->gross_pesewas,
        'currency' => 'GHS',
        'channel' => 'mobile_money',
    ];

    app(OrderFulfillmentService::class)->fulfill($order, $payment);

    Queue::assertPushed(SendTicketSms::class, static function (SendTicketSms $job) use ($order): bool {
        return $job->orderId === $order->id;
    });
});

test('SendTicketSms job dispatches formatted message with pass URL to gateway', function (): void {
    config()->set('sms.enabled', true);

    $order = Order::factory()->create([
        'organization_id' => $this->org->id,
        'customer_phone' => '0244998877',
        'reference' => 'TL-TEST-9988',
    ]);
    Attendee::factory()->count(2)->create([
        'organization_id' => $this->org->id,
        'order_id' => $order->id,
        'ticket_type_id' => $this->tier->id,
    ]);

    $mockGateway = Mockery::mock(SmsGatewayInterface::class);
    $mockGateway->shouldReceive('isEnabled')->andReturn(true);
    $mockGateway->shouldReceive('send')
        ->once()
        ->withArgs(function (string $phone, string $body, array $context): bool {
            return $phone === '0244998877'
                && str_contains($body, 'TL-TEST-9988')
                && str_contains($body, 'Charis Ministries')
                && str_contains($body, '2 pass(es)')
                && str_contains($body, 'tickets/TL-TEST-9988');
        })
        ->andReturn(true);

    app()->instance(SmsGatewayInterface::class, $mockGateway);

    (new SendTicketSms($order->id))->handle($mockGateway);
});
