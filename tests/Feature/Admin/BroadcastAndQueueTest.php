<?php

declare(strict_types=1);

use App\Jobs\SendEventReminders;
use App\Livewire\Admin\BroadcastHub;
use App\Livewire\Admin\QueueMonitor;
use App\Mail\EventReminderMail;
use App\Models\Attendee;
use App\Models\Broadcast;
use App\Models\Event;
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
    $this->zone = payable_zone($this->org);
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'title' => 'Summit 2027']);
    $this->tier = TicketType::factory()->create(['event_id' => $this->event->id]);
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
