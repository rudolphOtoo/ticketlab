<?php

declare(strict_types=1);

use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Admin\ZoneReassignments;
use App\Livewire\Ops\ScannerTerminal;
use App\Livewire\Public\TicketShow;
use App\Mail\ZoneChoiceRequestMail;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create(['subdivision_label' => 'Zone']);
    $this->kumasi = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Kumasi']);
    $this->capeCoast = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Cape Coast']);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id]);
    $this->tier = TicketType::factory()->create(['event_id' => $this->event->id, 'price' => 60]);

    // Sold before zones were required: paid, settled to the main account, two passes, no zone anywhere.
    $this->order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $this->org->id,
            'subdivision_id' => null,
            'payout_subaccount_code' => null,
            'gross_amount' => 123.70,
            'net_amount' => 120.00,
        ]);
    $this->passes = Attendee::factory()
        ->count(2)
        ->sequence(['name' => 'Ama Zoneless'], ['name' => 'Kofi Zoneless'])
        ->create([
            'organization_id' => $this->org->id,
            'order_id' => $this->order->id,
            'subdivision_id' => null,
            'ticket_type_id' => $this->tier->id,
        ]);
    $this->pass = $this->passes->first();

    $this->orgAdmin = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin']);
    $this->staff = fn(Subdivision $zone, string $role = 'usher'): User => User::factory()->create([
        'organization_id' => $this->org->id,
        'subdivision_id' => $zone->id,
        'role' => $role,
    ]);
    $this->scan = fn(User $user, Attendee $pass) => Livewire::actingAs($user)
        ->test(ScannerTerminal::class, ['eventId' => $this->event->id])
        ->call('validatePass', $pass->qr_code_hash);
});

test('assigning a zone on the roster changes which gate admits the pass and is audited', function () {
    ($this->scan)(($this->staff)($this->kumasi), $this->pass)->assertSet('scanState', 'wrong_zone');

    ActiveEvent::set($this->event);
    Livewire::actingAs($this->orgAdmin)
        ->test(AttendeeRoster::class)
        ->assertSee('2 paid passes have no zone')
        ->set('assignZoneId', $this->kumasi->id)
        ->call('assignZoneToAttendee', $this->pass->id)
        ->assertHasNoErrors();

    $pass = $this->pass->fresh();
    expect($pass->subdivision_id)
        ->toBe($this->kumasi->id)
        ->and($pass->zone_assigned_by)
        ->toBe($this->orgAdmin->id)
        ->and($pass->zone_assigned_via)
        ->toBe('admin')
        ->and($pass->checked_in_at)
        ->toBeNull();

    expect(AuditLog::query()->where('subject_id', $pass->id)->sole())
        ->organization_id->toBe($this->org->id)
        ->user_id->toBe($this->orgAdmin->id)
        ->action->toBe('attendee.zone_assigned');

    ($this->scan)(($this->staff)($this->capeCoast), $this->pass)->assertSet('scanState', 'wrong_zone');
    ($this->scan)(($this->staff)($this->kumasi), $this->pass)->assertSet('scanState', 'success');
});

test('bulk assignment zones every selected zoneless pass, skips zoned ones, and attributes the order', function () {
    $alreadyZoned = Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'order_id' => Order::factory()
            ->paid()
            ->create(['organization_id' => $this->org->id, 'subdivision_id' => $this->capeCoast->id])
            ->id,
        'subdivision_id' => $this->capeCoast->id,
        'ticket_type_id' => $this->tier->id,
    ]);
    ActiveEvent::set($this->event);

    Livewire::actingAs($this->orgAdmin)
        ->test(AttendeeRoster::class)
        ->set('zoneFilter', 'none')
        ->assertSee('Ama Zoneless')
        ->assertDontSee($alreadyZoned->name)
        ->set('selectedAttendeeIds', [...$this->passes->pluck('id')->all(), $alreadyZoned->id])
        ->set('assignZoneId', $this->kumasi->id)
        ->call('assignZoneToSelected')
        ->assertSet('selectedAttendeeIds', [])
        ->assertSee('2 passes assigned. 1 skipped');

    expect(Attendee::query()->whereKey($this->passes->pluck('id'))->pluck('subdivision_id')->unique()->all())
        ->toBe([$this->kumasi->id])
        ->and($alreadyZoned->fresh()->subdivision_id)
        ->toBe($this->capeCoast->id)
        // Reports attribute the order to Kumasi, but the record of where the money settled is untouched.
        ->and($this->order->fresh()->subdivision_id)
        ->toBe($this->kumasi->id)
        ->and($this->order->fresh()->payout_subaccount_code)
        ->toBeNull();
});

test('zone admins cannot assign zones and do not get the assignment panel', function () {
    $kumasiAdmin = ($this->staff)($this->kumasi, 'admin');
    ActiveEvent::set($this->event);

    Livewire::actingAs($kumasiAdmin)
        ->test(AttendeeRoster::class)
        ->assertViewHas('canAssignZones', false)
        ->assertDontSee('data-zoneless-panel', false)
        ->set('assignZoneId', $this->kumasi->id)
        ->call('assignZoneToAttendee', $this->pass->id)
        ->assertForbidden();

    expect($this->pass->fresh()->subdivision_id)->toBeNull()->and(AuditLog::query()->count())->toBe(0);

    $this->actingAs($kumasiAdmin)->get(route('admin.zone-reassignments'))->assertForbidden();
});

test('an organisation admin cannot assign another organisation\'s zone', function () {
    $foreignZone = Subdivision::factory()->create();
    ActiveEvent::set($this->event);

    Livewire::actingAs($this->orgAdmin)
        ->test(AttendeeRoster::class)
        ->set('assignZoneId', $foreignZone->id)
        ->call('assignZoneToAttendee', $this->pass->id)
        ->assertHasErrors('assignZoneId');

    expect($this->pass->fresh()->subdivision_id)->toBeNull();
});

test('the buyer chooses their zone once from the ticket page and the choice is then locked', function () {
    $this
        ->get(route('public.tickets.show', ['order' => $this->order->reference]))
        ->assertOk()
        ->assertSee('Choose your zone')
        ->assertSee('You can only choose once');

    $page = Livewire::test(TicketShow::class, ['order' => $this->order])
        ->call('chooseZone', $this->capeCoast->id)
        ->assertSee('Your passes now work at this gate')
        ->assertDontSee('data-choose-zone', false);

    expect(Attendee::query()->where('order_id', $this->order->id)->pluck('subdivision_id')->unique()->all())
        ->toBe([$this->capeCoast->id])
        ->and($this->pass->fresh()->zone_assigned_via)
        ->toBe('buyer')
        ->and(AuditLog::query()->where('action', 'attendee.zone_assigned')->whereNull('user_id')->count())
        ->toBe(2);

    // A second pick, even replayed directly, changes nothing.
    $page->call('chooseZone', $this->kumasi->id)->assertSee('already have a zone');

    expect($this->pass->fresh()->subdivision_id)->toBe($this->capeCoast->id);
    $this->get(route('public.tickets.show', ['order' => $this->order->reference]))->assertDontSee('Choose your zone');
});

test('a buyer cannot pick a zone from another organisation', function () {
    Livewire::test(TicketShow::class, ['order' => $this->order])
        ->call('chooseZone', Subdivision::factory()->create()->id)
        ->assertStatus(422);

    expect($this->pass->fresh()->subdivision_id)->toBeNull();
});

test('the zone-choice email command is a dry run by default and sends nothing', function () {
    Mail::fake();

    $this
        ->artisan('passes:request-zone-choice')
        ->expectsOutputToContain('Dry run: 1 email(s) would be queued. Nothing was sent.')
        ->assertSuccessful();

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
    expect($this->order->fresh()->metadata)->not->toHaveKey('zone_choice_requested_at');
});

test('the zone-choice email command sends once with --send and skips orders already asked', function () {
    Mail::fake();

    $this->artisan('passes:request-zone-choice', ['--send' => true])->assertSuccessful();
    Mail::assertQueued(
        ZoneChoiceRequestMail::class,
        fn(ZoneChoiceRequestMail $mail): bool => (
            $mail->hasTo($this->order->customer_email) && $mail->order->is($this->order)
        ),
    );

    $this
        ->artisan('passes:request-zone-choice', ['--send' => true])
        ->expectsOutputToContain('Queued 0 email(s)')
        ->assertSuccessful();
    Mail::assertQueuedCount(1);
});

test('the reassignment report shows each zone\'s share of money that settled to the main account', function () {
    ActiveEvent::set($this->event);
    Livewire::actingAs($this->orgAdmin)
        ->test(AttendeeRoster::class)
        ->set('assignZoneId', $this->kumasi->id)
        ->call('assignZoneToAttendee', $this->pass->id);

    Livewire::actingAs($this->orgAdmin)
        ->test(ZoneReassignments::class)
        ->assertSee('Money is not moved automatically')
        ->assertSee($this->order->reference)
        ->assertSee('Main account')
        // One of the order's two passes: half of the GHS 120 ticket value is owed to Kumasi.
        ->assertSee('GHS 60.00')
        ->assertSee('by ' . $this->orgAdmin->name);
});
