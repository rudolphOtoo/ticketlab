<?php

declare(strict_types=1);

use App\Livewire\Admin\AdminOverview;
use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Admin\BespokeStudio;
use App\Livewire\Admin\BroadcastHub;
use App\Livewire\Admin\DailySessionLedger;
use App\Livewire\Admin\EventCustomizer;
use App\Livewire\Admin\EventManager;
use App\Livewire\Admin\SubdivisionManager;
use App\Livewire\Admin\TicketManager;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @return list<array<string, string>>
 */
function zone_csv(StreamedResponse $response): array
{
    ob_start();
    $response->sendContent();
    $lines = array_values(array_filter(explode("\n", str_replace("\r", '', (string) ob_get_clean()))));
    $header = str_getcsv(ltrim(array_shift($lines) ?? '', "\u{FEFF}"));

    return array_map(static fn(string $line): array => array_combine($header, str_getcsv($line)), $lines);
}

beforeEach(function () {
    $this->org = Organization::factory()->create([
        'name' => 'Ghana Methodist Student Union',
        'subdivision_label' => 'Zone',
    ]);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'title' => 'GHAMSU Convention']);
    $this->tier = TicketType::factory()->create(['event_id' => $this->event->id, 'price' => 50]);

    // Accra: 1 pass (GHS 50, checked in). Kumasi: 2 passes (GHS 100). Cape Coast: 3 passes (GHS 150).
    $this->zones = [];
    foreach (['Accra' => 1, 'Kumasi' => 2, 'Cape Coast' => 3] as $name => $passes) {
        $zone = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => $name]);
        $order = Order::factory()
            ->paid()
            ->create([
                'organization_id' => $this->org->id,
                'subdivision_id' => $zone->id,
                'gross_amount' => 50 * $passes,
                'net_amount' => 50 * $passes,
            ]);

        foreach (range(1, $passes) as $n) {
            Attendee::factory()->create([
                'organization_id' => $this->org->id,
                'order_id' => $order->id,
                'subdivision_id' => $zone->id,
                'ticket_type_id' => $this->tier->id,
                'name' => "{$name} Delegate {$n}",
                'checked_in_at' => $name === 'Accra' ? now() : null,
            ]);
        }

        $this->zones[$name] = $zone;
    }

    $this->accraAdmin = User::factory()->create([
        'organization_id' => $this->org->id,
        'subdivision_id' => $this->zones['Accra']->id,
        'role' => 'admin',
    ]);
    $this->orgAdmin = User::factory()->create(['organization_id' => $this->org->id, 'role' => 'admin']);
    $this->kumasiPass = Attendee::query()->where('name', 'Kumasi Delegate 1')->firstOrFail();
});

test('an Accra zone admin sees only Accra on the roster, overview, ledger and broadcasts', function () {
    $this->actingAs($this->accraAdmin);
    ActiveEvent::set($this->event);

    $this->get(route('admin.attendees'))->assertOk()->assertSee('Accra Delegate 1')->assertDontSee('Kumasi Delegate');

    Livewire::test(AdminOverview::class)
        ->assertViewHas('stats', static fn(array $stats): bool => $stats['sold'] === 1 && $stats['revenue'] === 50.0)
        ->assertViewHas('zoneRows', []);

    Livewire::test(BroadcastHub::class)->assertViewHas('recipientCount', 1);

    Livewire::test(DailySessionLedger::class)->assertDontSee('Kumasi')->assertDontSee('Cape Coast');
});

test('an Accra zone admin gets 403 when asking for Kumasi records by query string', function (string $route) {
    $this
        ->actingAs($this->accraAdmin)
        ->get(route($route, ['subdivision_id' => $this->zones['Kumasi']->id]))
        ->assertForbidden();
})->with(['admin.attendees', 'admin.sessions', 'admin.exports', 'admin.index']);

test('an Accra zone admin cannot load or change a Kumasi attendee through Livewire actions', function () {
    $this->actingAs($this->accraAdmin);
    ActiveEvent::set($this->event);
    $roster = Livewire::test(AttendeeRoster::class);

    $roster->call('selectAttendee', $this->kumasiPass->id)->assertViewHas('selectedAttendee', null);
    $roster->call('toggleCheckIn', $this->kumasiPass->id);
    $roster->call('openEditAttendeeModal', $this->kumasiPass->id);
    $roster->call('voidPass', $this->kumasiPass->id);
    $roster->call('resendPassEmail', $this->kumasiPass->id);

    $fresh = $this->kumasiPass->fresh();
    expect($fresh)
        ->not
        ->toBeNull()
        ->and($fresh->checked_in_at)
        ->toBeNull()
        ->and($fresh->name)
        ->toBe('Kumasi Delegate 1');
});

test('an Accra zone admin exports only Accra passes', function () {
    $this->actingAs($this->accraAdmin);
    ActiveEvent::set($this->event);

    $rows = zone_csv(Livewire::test(AttendeeRoster::class)->instance()->exportCsv());

    expect(array_column($rows, 'Name'))->toBe(['Accra Delegate 1'])->and(array_column($rows, 'Zone'))->toBe(['Accra']);
});

test('an Accra zone admin is refused on every organisation-wide editing screen and action', function () {
    $this->actingAs($this->accraAdmin);
    ActiveEvent::set($this->event);

    foreach (['admin.events', 'admin.tickets', 'admin.studio', 'admin.studio.bespoke', 'admin.payouts'] as $route) {
        $this->get(route($route))->assertForbidden();
    }

    foreach ([EventManager::class, TicketManager::class, EventCustomizer::class, BespokeStudio::class] as $component) {
        Livewire::test($component)->assertForbidden();
    }

    Livewire::test(SubdivisionManager::class)
        ->assertDontSee('wire:click="editSubdivision', false)
        ->call('editSubdivision', $this->zones['Accra']->id)
        ->assertForbidden();

    $this->get(route('admin.index'))->assertOk()->assertDontSee('Tickets &amp; Pricing', false);
});

test('a later action request from a zone admin is refused even on a component that was already loaded', function () {
    ActiveEvent::set($this->event);

    // Loaded by the organisation admin, then replayed with the zone admin's session: the guard runs on every request.
    $tickets = Livewire::actingAs($this->orgAdmin)->test(TicketManager::class);
    $events = Livewire::actingAs($this->orgAdmin)->test(EventManager::class);

    $this->actingAs($this->accraAdmin);

    $tickets->call('toggleActive', $this->tier->id)->assertForbidden();
    $events->call('togglePublished', $this->event->id)->assertForbidden();

    expect($this->tier->fresh()->is_active)->toBeTrue()->and($this->event->fresh()->status)->toBe('published');
});

test('an organisation admin sees all three zones with correct per-zone totals', function () {
    $this->actingAs($this->orgAdmin);
    ActiveEvent::set($this->event);

    Livewire::test(AdminOverview::class)
        ->assertViewHas('zoneRows', [
            ['name' => 'Accra', 'sold' => 1, 'checked_in' => 1, 'revenue' => 50.0],
            ['name' => 'Cape Coast', 'sold' => 3, 'checked_in' => 0, 'revenue' => 150.0],
            ['name' => 'Kumasi', 'sold' => 2, 'checked_in' => 0, 'revenue' => 100.0],
        ])
        ->assertViewHas('stats', static fn(array $stats): bool => $stats['sold'] === 6 && $stats['revenue'] === 300.0)
        ->assertSeeInOrder(['Accra', 'Cape Coast', 'Kumasi', 'Total', '6', '1', 'GHS 300.00']);

    $rows = zone_csv(Livewire::test(AttendeeRoster::class)->instance()->exportCsv());
    expect($rows)->toHaveCount(6);

    $this->get(route('admin.events'))->assertOk();
});
