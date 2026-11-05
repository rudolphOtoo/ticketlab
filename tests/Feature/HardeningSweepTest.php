<?php

declare(strict_types=1);

use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Ops\ScannerTerminal;
use App\Livewire\Public\EventShow;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Reservation\TicketReservationService;
use App\Support\ActiveEvent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id]);
});

/*
 |--------------------------------------------------------------------------
 | Online checkout honours each tier's pause switch and sales window
 |--------------------------------------------------------------------------
 */

test('tiers that are paused or outside their sales window cannot be bought online', function (
    array $state,
    string $badge,
): void {
    $tier = TicketType::factory()->create(['event_id' => $this->event->id, 'name' => 'Closed Tier', ...$state]);

    $page = Livewire::test(EventShow::class, ['org_slug' => $this->org->slug, 'event_slug' => $this->event->slug])
        ->assertSet('ticketTypeId', null)
        ->call('selectTicketType', $tier->id)
        ->assertSet('ticketTypeId', null);

    if ($badge !== '') {
        $page->assertSee($badge);
    }

    $page
        ->set('ticketTypeId', $tier->id)
        ->set('customerName', 'Efua Mensah')
        ->set('customerEmail', 'efua@example.com')
        ->set('customerPhone', '0244000999')
        ->call('startCheckout')
        ->assertHasErrors('ticketTypeId');

    expect(Order::query()->count())->toBe(0)->and($tier->fresh()->available_quantity)->toBe($tier->available_quantity);
})->with([
    'paused' => [['is_active' => false], ''],
    'not yet on sale' => [['sales_start_at' => now()->addDay(), 'sales_end_at' => now()->addWeek()], 'Coming Soon'],
    'sales closed' => [['sales_start_at' => now()->subWeek(), 'sales_end_at' => now()->subDay()], 'Sales Closed'],
]);

test('the public checkout cannot be pointed at another event by tampering with its ids', function (string $property): void {
    $draft = Event::factory()->create(['status' => 'draft']);

    expect(fn() => Livewire::test(EventShow::class, [
        'org_slug' => $this->org->slug,
        'event_slug' => $this->event->slug,
    ])->set($property, $property === 'eventId' ? $draft->id : $draft->organization_id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['eventId', 'organizationId']);

test('paused tiers are hidden from the public page', function (): void {
    TicketType::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Secret Paused Tier',
        'is_active' => false,
    ]);

    $this
        ->get(route('public.event.show', ['org_slug' => $this->org->slug, 'event_slug' => $this->event->slug]))
        ->assertOk()
        ->assertDontSee('Secret Paused Tier');
});

/*
 |--------------------------------------------------------------------------
 | Complimentary passes and voids keep stock consistent
 |--------------------------------------------------------------------------
 */

function comp_pass(object $test, TicketType $tier, array $overrides = []): Testable
{
    ActiveEvent::set($test->event);

    return Livewire::actingAs($test->admin)
        ->test(AttendeeRoster::class)
        ->set('manual_name', 'Guest Speaker')
        ->set('manual_email', 'guest@example.com')
        ->set('manual_ticket_type_id', $tier->id)
        ->set($overrides)
        ->call('issueManualPass');
}

test('a complimentary pass for another organisation\'s zone is rejected', function (): void {
    $tier = TicketType::factory()->create(['event_id' => $this->event->id]);
    $foreignZone = Subdivision::factory()->create();

    comp_pass($this, $tier, ['manual_subdivision_id' => $foreignZone->id])->assertHasErrors('manual_subdivision_id');

    expect(Attendee::query()->count())->toBe(0);
});

test('a complimentary pass records its quantity and takes a seat while one is left', function (): void {
    $tier = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'total_capacity' => 1,
        'available_quantity' => 1,
    ]);

    comp_pass($this, $tier)->assertHasNoErrors();
    comp_pass($this, $tier)->assertHasNoErrors();

    expect($tier->fresh()->available_quantity)
        ->toBe(0)
        ->and(Order::query()->first()->metadata['quantity'])
        ->toBe(1)
        ->and(Attendee::query()->count())
        ->toBe(2);
});

test('voiding a pass restores one seat and never pushes stock above capacity', function (): void {
    $tier = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'total_capacity' => 1,
        'available_quantity' => 1,
    ]);
    comp_pass($this, $tier);
    comp_pass($this, $tier);
    [$first, $second] = Attendee::query()->orderBy('id')->get()->all();

    $roster = Livewire::actingAs($this->admin)->test(AttendeeRoster::class);
    $roster->call('voidPass', $first->id);
    $roster->call('voidPass', $first->id);
    $roster->call('voidPass', $second->id);

    expect($tier->fresh()->available_quantity)->toBe(1)->and(Attendee::query()->count())->toBe(0);
});

test('voiding one of two passes on a single-seat tier frees no seat while the other still holds it', function (): void {
    $tier = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'total_capacity' => 1,
        'available_quantity' => 1,
    ]);
    comp_pass($this, $tier);
    comp_pass($this, $tier);

    Livewire::actingAs($this->admin)
        ->test(AttendeeRoster::class)
        ->call('voidPass', Attendee::query()->orderBy('id')->value('id'));

    expect($tier->fresh()->available_quantity)->toBe(0)->and(Attendee::query()->count())->toBe(1);
});

test('an expiring hold never restocks seats that comps issued past sell-out have claimed', function (): void {
    $tier = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'total_capacity' => 2,
        'available_quantity' => 2,
    ]);
    $hold = app(TicketReservationService::class)->holdOrReuse($tier, 1, 'session-comp-race', null);
    comp_pass($this, $tier);
    comp_pass($this, $tier);
    $hold->update(['expires_at' => now()->subMinute()]);

    Artisan::call('reservations:release-expired');

    expect($tier->fresh()->available_quantity)->toBe(0);
});

/*
 |--------------------------------------------------------------------------
 | Platform settings: one lookup per key, correct defaults per caller
 |--------------------------------------------------------------------------
 */

test('missing platform settings are cached and each caller gets its own default', function (): void {
    $queries = 0;
    DB::listen(static function ($query) use (&$queries): void {
        $queries += str_contains($query->sql, 'platform_settings') ? 1 : 0;
    });

    expect(PlatformSetting::get('never_set_key', 'first-default'))
        ->toBe('first-default')
        ->and(PlatformSetting::get('never_set_key', 'second-default'))
        ->toBe('second-default')
        ->and($queries)
        ->toBe(1);

    PlatformSetting::set('never_set_key', 'stored');

    expect(PlatformSetting::get('never_set_key', 'ignored'))->toBe('stored');
});

/*
 |--------------------------------------------------------------------------
 | Org-scoped tools fail closed for accounts without an organisation
 |--------------------------------------------------------------------------
 */

test('a platform super admin without an organisation cannot see, void, or scan any tenant\'s passes', function (): void {
    $superAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $tier = TicketType::factory()->create(['event_id' => $this->event->id]);
    $pass = Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $tier->id,
        'subdivision_id' => null,
        'name' => 'Tenant Guest Abena',
    ]);

    Livewire::actingAs($superAdmin)
        ->test(AttendeeRoster::class)
        ->assertDontSee('Tenant Guest Abena')
        ->call('voidPass', $pass->id);

    Livewire::actingAs($superAdmin)
        ->test(ScannerTerminal::class)
        ->call('validatePass', $pass->qr_code_hash)
        ->assertSet('scanState', 'invalid');

    expect($pass->fresh())->not->toBeNull()->and($pass->fresh()->checked_in_at)->toBeNull();
});
