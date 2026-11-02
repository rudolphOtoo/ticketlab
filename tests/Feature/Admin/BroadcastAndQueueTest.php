<?php

declare(strict_types=1);

use App\Jobs\SendEventReminders;
use App\Jobs\SendTicketEmail;
use App\Livewire\Admin\BroadcastHub;
use App\Livewire\Admin\QueueMonitor;
use App\Mail\EventReminderMail;
use App\Mail\TicketIssuedMail;
use App\Models\Attendee;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'title' => 'Summit 2027']);
    $this->tier = TicketType::factory()->create(['event_id' => $this->event->id]);
});

test('ticket email job attaches a branded pdf with the passes', function (): void {
    Mail::fake();

    $order = Order::factory()->create(['organization_id' => $this->org->id, 'customer_email' => 'buyer@example.com']);
    Attendee::factory()
        ->count(2)
        ->create([
            'organization_id' => $this->org->id,
            'order_id' => $order->id,
            'ticket_type_id' => $this->tier->id,
        ]);

    new SendTicketEmail($order->id)->handle(app(\App\Services\Payment\QrCodeService::class));

    Mail::assertSent(TicketIssuedMail::class, static function (TicketIssuedMail $mail) use ($order): bool {
        $attachments = $mail->attachments();

        return (
            $mail->hasTo('buyer@example.com')
            && count($attachments) === 1
            && str_starts_with((string) $attachments[0]->as, "tickets-{$order->reference}")
        );
    });
});

test('ticket email job skips orders without passes', function (): void {
    Mail::fake();

    $order = Order::factory()->create(['organization_id' => $this->org->id]);

    new SendTicketEmail($order->id)->handle(app(\App\Services\Payment\QrCodeService::class));

    Mail::assertNothingSent();
});

test('broadcast hub dispatches one batched job per unique ticket holder', function (): void {
    Bus::fake();

    Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->tier->id,
        'email' => 'ama@example.com',
    ]);
    Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->tier->id,
        'email' => 'AMA@example.com',
    ]);
    Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->tier->id,
        'email' => 'kofi@example.com',
    ]);
    // Another tenant's attendee must never be emailed.
    Attendee::factory()->create(['email' => 'outsider@example.com']);

    Livewire::actingAs($this->admin)
        ->test(BroadcastHub::class)
        ->set('subject', 'Gates open at 8')
        ->set('body', 'See you tomorrow!')
        ->call('send')
        ->assertHasNoErrors();

    Bus::assertBatched(
        static fn(PendingBatch $batch): bool => (
            $batch->name === 'Broadcast: Gates open at 8'
            && $batch->jobs->count() === 2
            && $batch->jobs->every(
                static fn($job): bool => $job instanceof SendEventReminders && $job->email !== 'outsider@example.com',
            )
        ),
    );

    expect(Broadcast::query()->firstOrFail())
        ->organization_id->toBe($this->org->id)
        ->event_id->toBe($this->event->id)
        ->recipients_count->toBe(2);
});

test('broadcast audience filters by check-in status', function (): void {
    Bus::fake();

    Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $this->tier->id,
        'checked_in_at' => now(),
    ]);
    Attendee::factory()->create(['organization_id' => $this->org->id, 'ticket_type_id' => $this->tier->id]);

    Livewire::actingAs($this->admin)
        ->test(BroadcastHub::class)
        ->set('audience', 'pending')
        ->set('subject', 'Still coming?')
        ->set('body', 'Doors close at noon.')
        ->call('send');

    Bus::assertBatched(static fn(PendingBatch $batch): bool => $batch->jobs->count() === 1);
});

test('broadcast with no recipients is rejected', function (): void {
    Bus::fake();

    Livewire::actingAs($this->admin)
        ->test(BroadcastHub::class)
        ->set('subject', 'Hello')
        ->set('body', 'Nobody here')
        ->call('send')
        ->assertHasErrors(['audience']);

    Bus::assertNothingBatched();
});

test('reminder job sends the broadcast and respects batch cancellation', function (): void {
    Mail::fake();

    $broadcast = Broadcast::query()->create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'subject' => 'Reminder',
        'body' => 'Tomorrow!',
    ]);

    new SendEventReminders($broadcast->id, 'guest@example.com', 'Guest')->handle();

    Mail::assertSent(
        EventReminderMail::class,
        static fn(EventReminderMail $mail): bool => $mail->hasTo('guest@example.com') && $mail->hasSubject('Reminder'),
    );
});

test('queue monitor shows tenant batches but hides global failed jobs from tenant admins', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => 'failed-uuid-1',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendTicketEmail']),
        'exception' => 'RuntimeException: secret tenant payload',
        'failed_at' => now(),
    ]);

    Livewire::actingAs($this->admin)
        ->test(QueueMonitor::class)
        ->assertSee('Failed jobs')
        ->assertDontSee('secret tenant payload')
        ->call('flushFailed')
        ->assertForbidden();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

test('super admins can inspect and flush failed jobs', function (): void {
    $superAdmin = User::factory()->create()->assignMembership('super_admin', null);

    DB::table('failed_jobs')->insert([
        'uuid' => 'failed-uuid-2',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendEventReminders']),
        'exception' => "RuntimeException: SMTP down\n#0 stack",
        'failed_at' => now(),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(QueueMonitor::class)
        ->assertSee('SendEventReminders')
        ->assertSee('SMTP down')
        ->call('flushFailed');

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

test('paying for an order queues the ticket email exactly once', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    $this->event->update(['status' => 'published', 'slug' => 'summit-2027']);
    $this->tier->update(['price' => 50, 'total_capacity' => 10, 'available_quantity' => 10, 'is_active' => true]);

    $component = Livewire::test(App\Livewire\Public\EventShow::class, [
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

    $fulfillment = app(App\Services\Payment\OrderFulfillmentService::class);
    $fulfillment->fulfill($order, $payment);
    $fulfillment->fulfill($order->fresh(), $payment);

    Illuminate\Support\Facades\Queue::assertPushed(SendTicketEmail::class, 1);
    Illuminate\Support\Facades\Queue::assertPushed(
        SendTicketEmail::class,
        static fn(SendTicketEmail $job): bool => $job->orderId === $order->id,
    );
});
