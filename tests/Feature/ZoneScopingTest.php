<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Livewire\Admin\AdminOverview;
use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Admin\BroadcastHub;
use App\Livewire\Admin\DailySessionLedger;
use App\Livewire\Admin\DataExportCentral;
use App\Livewire\Admin\SubdivisionManager;
use App\Livewire\Ops\CashDeskTerminal;
use App\Livewire\Ops\ScannerTerminal;
use App\Models\Attendee;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\SessionLedger;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

/*
 |--------------------------------------------------------------------------
 | Zone-bound admins only ever see and manage their own zone
 |--------------------------------------------------------------------------
 | One organisation, one event, two zones. Zone A sells GHS 100 online, Zone B
 | sells GHS 250 online plus GHS 40 at the cash desk. Organisation-wide admins
 | see everything; the Zone A admin must never see a Zone B record or figure.
 */

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->create(['organization_id' => $this->org->id]);
    $this->tier = TicketType::factory()->create(['event_id' => $this->event->id, 'name' => 'Delegate']);

    $this->zoneA = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Zone Alpha']);
    $this->zoneB = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Zone Bravo']);

    $this->orgAdmin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->zoneAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $this->zoneA->id);

    $this->passA = zone_pass($this, $this->zoneA, 'Ama Alpha', 100.0, PaymentMethod::MobileMoney);
    $this->passB = zone_pass($this, $this->zoneB, 'Kwesi Bravo', 250.0, PaymentMethod::MobileMoney);
    $this->cashB = zone_pass($this, $this->zoneB, 'Yaw Cash', 40.0, PaymentMethod::PhysicalCash);
});

function zone_pass(object $test, Subdivision $zone, string $name, float $amount, PaymentMethod $method): Attendee
{
    $order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $test->org->id,
            'subdivision_id' => $zone->id,
            'customer_name' => $name,
            'gross_amount' => $amount,
            'net_amount' => $amount,
            'payment_method' => $method,
        ]);

    return Attendee::factory()
        ->checkedIn()
        ->create([
            'order_id' => $order->id,
            'organization_id' => $test->org->id,
            'subdivision_id' => $zone->id,
            'ticket_type_id' => $test->tier->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
        ]);
}

function streamed_csv(\Symfony\Component\HttpFoundation\StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

test('the attendee roster lists only the zone admin\'s own registrations', function (): void {
    Livewire::actingAs($this->zoneAdmin)
        ->test(AttendeeRoster::class)
        ->assertSee('Ama Alpha')
        ->assertDontSee('Kwesi Bravo')
        ->assertDontSee('Yaw Cash')
        ->assertViewHas('totalCount', 1);

    Livewire::actingAs($this->orgAdmin)
        ->test(AttendeeRoster::class)
        ->assertSee('Ama Alpha')
        ->assertSee('Kwesi Bravo')
        ->assertViewHas('totalCount', 3);
});

test('a zone admin cannot act on another zone\'s attendee by id', function (): void {
    Livewire::actingAs($this->zoneAdmin)
        ->test(AttendeeRoster::class)
        ->call('selectAttendee', $this->passB->id)
        ->assertViewHas('selectedAttendee', null)
        ->call('toggleCheckIn', $this->passB->id)
        ->call('voidPass', $this->passB->id)
        ->call('openEditAttendeeModal', $this->passB->id)
        ->assertSet('editingAttendeeId', null);

    $passB = $this->passB->fresh();

    expect($passB)->not->toBeNull()->and($passB?->checked_in_at)->not->toBeNull();
});

test('the roster csv export only contains the zone admin\'s own attendees', function (): void {
    $csv = streamed_csv(Livewire::actingAs($this->zoneAdmin)->test(AttendeeRoster::class)->instance()->exportCsv());

    expect($csv)->toContain('Ama Alpha')->not->toContain('Kwesi Bravo')->not->toContain('Yaw Cash');
});

test('the data export csv is zone scoped and the full backup is organisation-admin only', function (): void {
    $zoneExport = Livewire::actingAs($this->zoneAdmin)->test(DataExportCentral::class, [
        'eventId' => $this->event->id,
    ]);

    expect(streamed_csv($zoneExport->instance()->exportAttendeeCsv()))
        ->toContain('Ama Alpha')
        ->not->toContain('Kwesi Bravo')
        ->not->toContain('kwesi.bravo@example.com');

    expect(fn() => $zoneExport->instance()->exportJsonBackup())
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    $orgExport = Livewire::actingAs($this->orgAdmin)->test(DataExportCentral::class, ['eventId' => $this->event->id]);

    expect(streamed_csv($orgExport->instance()->exportAttendeeCsv()))->toContain('Ama Alpha')->toContain('Kwesi Bravo');
    expect($orgExport->instance()->exportJsonBackup()->getStatusCode())->toBe(200);
});

test('overview metrics count only the zone admin\'s registrations and revenue', function (): void {
    Livewire::actingAs($this->zoneAdmin)
        ->test(AdminOverview::class)
        ->assertViewHas(
            'stats',
            static fn(array $stats): bool => (
                $stats['sold'] === 1
                && $stats['checked_in'] === 1
                && $stats['revenue'] === 100.0
            ),
        );

    Livewire::actingAs($this->orgAdmin)
        ->test(AdminOverview::class)
        ->assertViewHas(
            'stats',
            static fn(array $stats): bool => (
                $stats['sold'] === 3
                && $stats['checked_in'] === 3
                && $stats['revenue'] === 390.0
            ),
        );
});

test('the session ledger reports zone revenue, turnout and gate feed for zone admins', function (): void {
    Livewire::actingAs($this->zoneAdmin)
        ->test(DailySessionLedger::class)
        ->assertViewHas('totalOnlinePaystack', 100.0)
        ->assertViewHas('totalGateCash', 0.0)
        ->assertViewHas('turnoutCount', 1)
        ->assertViewHas('capacityTotal', 1)
        ->assertSee('Ama Alpha')
        ->assertDontSee('Kwesi Bravo')
        ->assertDontSee('Yaw Cash');

    Livewire::actingAs($this->orgAdmin)
        ->test(DailySessionLedger::class)
        ->assertViewHas('totalOnlinePaystack', 350.0)
        ->assertViewHas('totalGateCash', 40.0)
        ->assertViewHas('turnoutCount', 3);
});

test('zone and organisation closeouts are separate and cannot lock each other', function (): void {
    $orgLedger = Livewire::actingAs($this->orgAdmin)->test(DailySessionLedger::class);
    $orgSession = SessionLedger::query()->findOrFail($orgLedger->get('activeSessionId'));

    $zoneLedger = Livewire::actingAs($this->zoneAdmin)->test(DailySessionLedger::class);
    $zoneSession = SessionLedger::query()->findOrFail($zoneLedger->get('activeSessionId'));

    expect($orgSession->subdivision_id)
        ->toBeNull()
        ->and($zoneSession->subdivision_id)
        ->toBe($this->zoneA->id)
        ->and($zoneSession->session_name)
        ->toStartWith('Zone Alpha · ');

    // The session id is client-writable: pointing it at the organisation closeout must not lock it.
    $zoneLedger->set('activeSessionId', $orgSession->id)->call('lockSession');
    expect($orgSession->fresh()?->locked_at)->toBeNull();

    $zoneLedger->set('activeSessionId', $zoneSession->id)->call('lockSession');
    $zoneSession->refresh();

    expect($zoneSession->locked_at)
        ->not
        ->toBeNull()
        ->and((float) $zoneSession->total_online_momo)
        ->toBe(100.0)
        ->and((float) $zoneSession->total_cash)
        ->toBe(0.0)
        ->and($zoneSession->total_attendees)
        ->toBe(1);
});

test('the cash desk recent sales and scanner turnout stay inside the operator\'s zone', function (): void {
    $cashierB = User::factory()->create()->assignMembership('staff', $this->org->id, $this->zoneB->id);
    $cashierA = User::factory()->create()->assignMembership('staff', $this->org->id, $this->zoneA->id);

    expect(Livewire::actingAs($cashierA)->test(CashDeskTerminal::class)->get('recentCashSales'))->toBe([]);
    expect(Livewire::actingAs($cashierB)->test(CashDeskTerminal::class)->get('recentCashSales'))->toHaveCount(1);

    Livewire::actingAs($cashierA)
        ->test(ScannerTerminal::class)
        ->assertViewHas('totalCheckedIn', 1)
        ->assertViewHas('totalExpected', 1);
});

test('broadcast history only shows broadcasts sent from the zone admin\'s zone', function (): void {
    $zoneBAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $this->zoneB->id);

    foreach ([[$this->zoneAdmin, 'Alpha bus times'], [$zoneBAdmin, 'Bravo bus times']] as [$sender, $subject]) {
        Broadcast::query()->create([
            'organization_id' => $this->org->id,
            'event_id' => $this->event->id,
            'user_id' => $sender->id,
            'subject' => $subject,
            'body' => 'Details inside.',
            'audience' => 'all',
            'recipients_count' => 1,
        ]);
    }

    Livewire::actingAs($this->zoneAdmin)
        ->test(BroadcastHub::class)
        ->assertSee('Alpha bus times')
        ->assertDontSee('Bravo bus times');

    Livewire::actingAs($this->orgAdmin)
        ->test(BroadcastHub::class)
        ->assertSee('Alpha bus times')
        ->assertSee('Bravo bus times');
});

test('zone admins only manage their own zone and cannot create or delete zones', function (): void {
    $manager = Livewire::actingAs($this->zoneAdmin)
        ->test(SubdivisionManager::class)
        ->assertSee('Zone Alpha')
        ->assertDontSee('Zone Bravo')
        ->call('editSubdivision', $this->zoneB->id)
        ->assertSet('editingSubdivisionId', null);

    $manager->call('deleteSubdivision', $this->zoneB->id)->assertForbidden();
    Livewire::actingAs($this->zoneAdmin)->test(SubdivisionManager::class)->call('openCreateModal')->assertForbidden();

    expect(Subdivision::query()->whereKey($this->zoneB->id)->exists())->toBeTrue();

    Livewire::actingAs($this->orgAdmin)
        ->test(SubdivisionManager::class)
        ->assertSee('Zone Alpha')
        ->assertSee('Zone Bravo');
});

test('complimentary passes issued by a zone admin always land in their own zone', function (): void {
    Livewire::actingAs($this->zoneAdmin)
        ->test(AttendeeRoster::class)
        ->call('openManualPassModal')
        ->set('manual_name', 'Guest Speaker')
        ->set('manual_email', 'speaker@example.com')
        ->set('manual_ticket_type_id', $this->tier->id)
        ->set('manual_subdivision_id', $this->zoneB->id)
        ->call('issueManualPass')
        ->assertHasNoErrors();

    $pass = Attendee::query()->where('email', 'speaker@example.com')->sole();

    expect($pass->subdivision_id)->toBe($this->zoneA->id)->and($pass->order?->subdivision_id)->toBe($this->zoneA->id);
});

test('requesting another zone\'s records by query string is forbidden', function (string $route): void {
    $this
        ->actingAs($this->zoneAdmin)
        ->get(route($route, ['subdivision_id' => $this->zoneB->id]))
        ->assertForbidden();
    $this
        ->actingAs($this->zoneAdmin)
        ->get(route($route, ['subdivision_id' => $this->zoneA->id]))
        ->assertOk();
})->with(['admin.attendees', 'admin.sessions', 'admin.index', 'admin.exports']);
