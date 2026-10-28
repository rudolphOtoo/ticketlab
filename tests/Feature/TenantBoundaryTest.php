<?php

declare(strict_types=1);

use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Admin\DailySessionLedger;
use App\Livewire\Ops\CashDeskTerminal;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use App\Models\SessionLedger;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->adminA = User::factory()->create(['organization_id' => $this->orgA->id, 'role' => 'admin']);
});

test('users without an organization are refused on every admin and ops surface', function (string $route) {
    $orphan = User::factory()->create(['organization_id' => null, 'role' => 'gate_usher']);

    $this->actingAs($orphan)->get(route($route))->assertForbidden();
})->with(['admin.attendees', 'admin.sessions', 'ops.scanner', 'ops.cash-desk']);

test('ushers keep ops access but remain blocked from admin surfaces', function () {
    $usher = User::factory()->create(['organization_id' => $this->orgA->id, 'role' => 'usher']);

    $this->actingAs($usher)->get(route('ops.scanner'))->assertOk();
    $this->actingAs($usher)->get(route('admin.attendees'))->assertForbidden();
});

test('self-registered accounts are created as attendees', function () {
    if (!Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration is disabled.');
    }

    $this->post(route('register.store'), [
        'name' => 'Walk Up Buyer',
        'email' => 'buyer@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::query()->where('email', 'buyer@example.com')->value('role'))->toBe('attendee');
});

test('roster detail modal cannot load an attendee from another organization', function () {
    $foreign = Attendee::factory()->create(['organization_id' => $this->orgB->id]);

    Livewire::actingAs($this->adminA)
        ->test(AttendeeRoster::class)
        ->call('selectAttendee', $foreign->id)
        ->assertViewHas('selectedAttendee', fn($attendee) => $attendee === null);
});

test('session ledger ignores a tampered session id from another organization', function () {
    Storage::fake('local');

    $foreign = SessionLedger::create([
        'organization_id' => $this->orgB->id,
        'session_name' => 'Org B Day 1',
        'opened_at' => now(),
    ]);

    Livewire::actingAs($this->adminA)
        ->test(DailySessionLedger::class)
        ->set('activeSessionId', $foreign->id)
        ->call('lockSession')
        ->assertNotDispatched('session-locked')
        ->call('downloadPdfReport')
        ->assertNoFileDownloaded();

    expect($foreign->fresh()->locked_at)->toBeNull();
});

test('cash desk refuses a tampered tier that belongs to another event', function () {
    $eventA = Event::factory()->create(['organization_id' => $this->orgA->id]);
    TicketType::factory()->create(['event_id' => $eventA->id, 'price' => 50]);
    $foreignTier = TicketType::factory()->create([
        'event_id' => Event::factory()->create(['organization_id' => $this->orgB->id])->id,
        'price' => 50,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    Livewire::actingAs($this->adminA)
        ->test(CashDeskTerminal::class, ['eventId' => $eventA->id])
        ->set('selectedTierId', $foreignTier->id)
        ->set('cashTendered', '100.00')
        ->call('processCashSale')
        ->assertHasErrors('tier');

    expect($foreignTier->fresh()->available_quantity)->toBe(100);
    $this->assertDatabaseCount('orders', 0);
});
