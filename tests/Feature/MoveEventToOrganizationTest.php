<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\EventFaq;
use App\Models\EventFormField;
use App\Models\EventSchedule;
use App\Models\EventSpeaker;
use App\Models\EventZone;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\SessionLedger;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\TicketTypeZoneCapacity;
use App\Models\User;

/**
 * The production situation: the Methodist convention was created by the seeded summit-org admin, so it and all of
 * its sales live under "Perez Dome & Summit Global", next to that organisation's own real event.
 */
beforeEach(function (): void {
    $this->summit = Organization::factory()->create(['name' => 'Perez Dome & Summit Global', 'slug' => 'summit-org']);
    $this->summitAccra = Subdivision::factory()->create([
        'organization_id' => $this->summit->id,
        'name' => 'Accra Central',
    ]);
    $this->summitKumasi = Subdivision::factory()->create(['organization_id' => $this->summit->id, 'name' => 'Kumasi']);

    $this->gmsu = Organization::factory()->create(['name' => 'Ghana Methodist Student Union', 'slug' => 'gmsu']);
    $this->gmsuAccra = Subdivision::factory()->create(['organization_id' => $this->gmsu->id, 'name' => 'Accra']);
    $this->gmsuKumasi = Subdivision::factory()->create(['organization_id' => $this->gmsu->id, 'name' => 'kumasi']);

    $this->methodist = Event::factory()->create([
        'organization_id' => $this->summit->id,
        'title' => 'GHAMSU Annual Convention',
        'slug' => 'ghamsu-convention',
        'status' => 'published',
    ]);
    $this->summit->update(['settings' => ['featured_event_id' => $this->methodist->id]]);
    $tier = TicketType::factory()->create(['event_id' => $this->methodist->id, 'name' => 'Delegate']);

    EventZone::query()->create([
        'organization_id' => $this->summit->id,
        'event_id' => $this->methodist->id,
        'subdivision_id' => $this->summitAccra->id,
        'venue' => 'Wesley Hall',
    ]);
    TicketTypeZoneCapacity::query()->create([
        'organization_id' => $this->summit->id,
        'ticket_type_id' => $tier->id,
        'subdivision_id' => $this->summitKumasi->id,
        'capacity' => 40,
    ]);
    EventSpeaker::factory()->create(['organization_id' => $this->summit->id, 'event_id' => $this->methodist->id]);
    EventSchedule::factory()->create(['organization_id' => $this->summit->id, 'event_id' => $this->methodist->id]);
    EventFaq::factory()->create(['organization_id' => $this->summit->id, 'event_id' => $this->methodist->id]);
    EventFormField::factory()->create(['organization_id' => $this->summit->id, 'event_id' => $this->methodist->id]);
    Broadcast::query()->create([
        'organization_id' => $this->summit->id,
        'event_id' => $this->methodist->id,
        'subject' => 'Welcome',
        'body' => 'See you there',
        'recipients_count' => 2,
    ]);

    // A paid Kumasi order with its pass, a pending Accra order still holding seats, and a zoneless legacy pass.
    $paid = Order::factory()->create([
        'organization_id' => $this->summit->id,
        'subdivision_id' => $this->summitKumasi->id,
        'payment_status' => OrderStatus::Paid,
        'payout_subaccount_code' => 'ACCT_mainaccount',
    ]);
    $this->kumasiPass = Attendee::factory()->create([
        'organization_id' => $this->summit->id,
        'order_id' => $paid->id,
        'subdivision_id' => $this->summitKumasi->id,
        'ticket_type_id' => $tier->id,
    ]);
    $hold = Reservation::factory()->create([
        'organization_id' => $this->summit->id,
        'ticket_type_id' => $tier->id,
        'subdivision_id' => $this->summitAccra->id,
    ]);
    Order::factory()->create([
        'organization_id' => $this->summit->id,
        'subdivision_id' => $this->summitAccra->id,
        'reservation_id' => $hold->id,
    ]);
    $legacy = Order::factory()->create([
        'organization_id' => $this->summit->id,
        'subdivision_id' => null,
        'payment_status' => OrderStatus::Paid,
    ]);
    Attendee::factory()->create([
        'organization_id' => $this->summit->id,
        'order_id' => $legacy->id,
        'subdivision_id' => null,
        'ticket_type_id' => $tier->id,
    ]);
    AuditLog::query()->create([
        'organization_id' => $this->summit->id,
        'action' => 'attendee.zone_assigned',
        'subject_type' => 'attendee',
        'subject_id' => $this->kumasiPass->id,
    ]);

    // summit-org's own event, sales and ledger must not move.
    $this->summitEvent = Event::factory()->create([
        'organization_id' => $this->summit->id,
        'title' => 'Kingdom Summit',
    ]);
    $summitTier = TicketType::factory()->create(['event_id' => $this->summitEvent->id]);
    $this->summitPass = Attendee::factory()->create([
        'organization_id' => $this->summit->id,
        'order_id' => Order::factory()->create([
            'organization_id' => $this->summit->id,
            'subdivision_id' => $this->summitKumasi->id,
        ])->id,
        'subdivision_id' => $this->summitKumasi->id,
        'ticket_type_id' => $summitTier->id,
    ]);
    $this->ledger = SessionLedger::query()->create([
        'organization_id' => $this->summit->id,
        'session_name' => 'Day 1',
        'opened_at' => now(),
    ]);
});

/**
 * @return array<string, int>
 */
function rows_owned_by(Organization $org): array
{
    return collect([
        'events' => Event::class,
        'event_zones' => EventZone::class,
        'capacities' => TicketTypeZoneCapacity::class,
        'reservations' => Reservation::class,
        'orders' => Order::class,
        'attendees' => Attendee::class,
        'speakers' => EventSpeaker::class,
        'schedules' => EventSchedule::class,
        'faqs' => EventFaq::class,
        'form_fields' => EventFormField::class,
        'broadcasts' => Broadcast::class,
        'audit_logs' => AuditLog::class,
        'ledgers' => SessionLedger::class,
    ])->map(static fn(string $model): int => $model::query()->where('organization_id', $org->id)->count())->all();
}

test('the dry run prints counts and changes nothing', function (): void {
    $before = [rows_owned_by($this->summit), rows_owned_by($this->gmsu)];

    $this
        ->artisan('tenancy:move-event', [
            'event' => $this->methodist->id,
            '--to-org' => 'gmsu',
            '--map' => ['Accra Central=Accra'],
        ])
        ->expectsOutputToContain('GHAMSU Annual Convention')
        ->expectsOutputToContain('Dry run: nothing was changed')
        ->assertSuccessful();

    expect([rows_owned_by($this->summit), rows_owned_by($this->gmsu)])
        ->toBe($before)
        ->and($this->methodist->fresh()->organization_id)
        ->toBe($this->summit->id);
});

test('it refuses when a zone cannot be mapped, and changes nothing', function (): void {
    $this
        ->artisan('tenancy:move-event', ['event' => $this->methodist->id, '--to-org' => 'gmsu', '--execute' => true])
        ->expectsOutputToContain('Cannot map these zones to gmsu: Accra Central')
        ->assertFailed();

    expect($this->methodist->fresh()->organization_id)->toBe($this->summit->id);
});

test('it refuses an unknown target, the same organisation, or a slug the target already uses', function (): void {
    $this->artisan('tenancy:move-event', ['event' => $this->methodist->id, '--to-org' => 'nope'])->assertFailed();
    $this->artisan('tenancy:move-event', ['event' => $this->methodist->id, '--to-org' => 'summit-org'])->assertFailed();

    Event::factory()->create(['organization_id' => $this->gmsu->id, 'slug' => 'ghamsu-convention']);
    $this->artisan('tenancy:move-event', ['event' => 'ghamsu-convention', '--to-org' => 'gmsu'])->assertFailed();
});

test('--execute moves the event and every child row, remapping zones, and leaves the other event alone', function (): void {
    $this
        ->artisan('tenancy:move-event', [
            'event' => $this->methodist->id,
            '--to-org' => 'gmsu',
            '--map' => ['Accra Central=Accra'],
            '--execute' => true,
        ])
        ->expectsOutputToContain('Moved.')
        ->assertSuccessful();

    expect(rows_owned_by($this->gmsu))->toBe([
        'events' => 1,
        'event_zones' => 1,
        'capacities' => 1,
        'reservations' => 1,
        'orders' => 3,
        'attendees' => 2,
        'speakers' => 1,
        'schedules' => 1,
        'faqs' => 1,
        'form_fields' => 1,
        'broadcasts' => 1,
        'audit_logs' => 1,
        'ledgers' => 0,
    ]);

    // Zones remapped by explicit map (Accra Central → Accra) and by case-insensitive name (Kumasi → kumasi).
    expect($this->kumasiPass->fresh()->subdivision_id)
        ->toBe($this->gmsuKumasi->id)
        ->and(EventZone::query()->sole()->subdivision_id)
        ->toBe($this->gmsuAccra->id)
        ->and(TicketTypeZoneCapacity::query()->sole()->subdivision_id)
        ->toBe($this->gmsuKumasi->id)
        ->and(Reservation::query()->sole()->subdivision_id)
        ->toBe($this->gmsuAccra->id)
        // Where the money settled is history and is never rewritten.
        ->and($this->kumasiPass->fresh()->order->payout_subaccount_code)
        ->toBe('ACCT_mainaccount');

    // summit-org keeps its own event, pass and ledger, and no longer features the Methodist event.
    expect($this->summitEvent->fresh()->organization_id)
        ->toBe($this->summit->id)
        ->and($this->summitPass->fresh()->subdivision_id)
        ->toBe($this->summitKumasi->id)
        ->and($this->ledger->fresh()->organization_id)
        ->toBe($this->summit->id)
        ->and($this->summit->fresh()->settings)
        ->not->toHaveKey('featured_event_id');

    // The public page now carries the Methodist identity only.
    $this
        ->get('/gmsu/ghamsu-convention')
        ->assertOk()
        ->assertSee('Ghana Methodist Student Union')
        ->assertDontSee('Perez Dome');
    $this->get('/summit-org/ghamsu-convention')->assertNotFound();
});

test('--with-ledgers is refused while the source organisation has passes for other events', function (): void {
    $this
        ->artisan('tenancy:move-event', [
            'event' => $this->methodist->id,
            '--to-org' => 'gmsu',
            '--map' => ['Accra Central=Accra'],
            '--with-ledgers' => true,
            '--execute' => true,
        ])
        ->expectsOutputToContain('--with-ledgers refused')
        ->assertFailed();

    expect($this->methodist->fresh()->organization_id)->toBe($this->summit->id);
});

test('--with-ledgers moves the ledgers when this is the source organisation\'s only event with passes', function (): void {
    $this->summitPass->delete();
    User::factory()->create()->assignMembership('admin', $this->summit->id);

    $this->artisan('tenancy:move-event', [
        'event' => $this->methodist->id,
        '--to-org' => 'gmsu',
        '--map' => ['Accra Central=Accra'],
        '--with-ledgers' => true,
        '--execute' => true,
    ])->assertSuccessful();

    expect($this->ledger->fresh()->organization_id)->toBe($this->gmsu->id);
});
