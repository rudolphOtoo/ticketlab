<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Admin\DailySessionLedger;
use App\Livewire\Ops\CashDeskTerminal;
use App\Mail\TicketIssuedMail;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\SessionLedger;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Payment\TicketPassPdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
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
})->with(['admin.attendees', 'admin.sessions', 'admin.studio.marketing', 'ops.scanner', 'ops.cash-desk']);

test('ushers keep ops access but remain blocked from admin surfaces', function () {
    $usher = User::factory()->create(['organization_id' => $this->orgA->id, 'role' => 'usher']);

    $this->actingAs($usher)->get(route('ops.scanner'))->assertOk();
    $this->actingAs($usher)->get(route('admin.attendees'))->assertForbidden();
    $this->actingAs($usher)->get(route('admin.studio.marketing'))->assertForbidden();
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

    expect(User::query()->where('email', 'buyer@example.com')->value('role'))
        ->toBe('attendee')
        ->and(Organization::query()->whereKeyNot([$this->orgA->id, $this->orgB->id])->count())
        ->toBe(0);
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

/**
 * A fully separate church tenant: organisation, admin, zone, published event, tier and one paid pass.
 *
 * @return array{org: Organization, admin: User, event: Event, order: Order}
 */
function branded_church(string $name, string $slug, string $eventTitle, string $venue): array
{
    $org = Organization::factory()->create(['name' => $name, 'slug' => $slug]);
    $admin = User::factory()->create(['organization_id' => $org->id, 'role' => 'admin']);
    $zone = Subdivision::factory()->create(['organization_id' => $org->id]);
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => $eventTitle,
        'slug' => $slug . '-conference',
        'venue' => $venue,
        'status' => 'published',
        'is_public' => true,
    ]);
    $tier = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'Delegate Pass']);
    $order = Order::factory()->create([
        'organization_id' => $org->id,
        'subdivision_id' => $zone->id,
        'payment_status' => OrderStatus::Paid,
        'paid_at' => now(),
    ]);
    Attendee::factory()->create([
        'order_id' => $order->id,
        'organization_id' => $org->id,
        'subdivision_id' => $zone->id,
        'ticket_type_id' => $tier->id,
    ]);

    return ['org' => $org, 'admin' => $admin, 'event' => $event, 'order' => $order];
}

/**
 * @return array{0: array{org: Organization, admin: User, event: Event, order: Order}, 1: array{org: Organization, admin: User, event: Event, order: Order}}
 */
function two_churches(): array
{
    return [
        branded_church('Ghana Methodist Student Union', 'gmsu', 'GMSU Annual Convention', 'Wesley Girls Hall'),
        branded_church('Presbyterian Youth Fellowship', 'pyf', 'PYF Youth Camp', 'Ramseyer Centre'),
    ];
}

/**
 * Every identity marker of a tenant that must never appear on another tenant's surfaces.
 *
 * @param array{org: Organization, event: Event} $church
 *
 * @return list<string>
 */
function identity_of(array $church): array
{
    return [
        $church['org']->name,
        $church['event']->title,
        $church['event']->venue ?? '',
        '/' . $church['org']->slug . '/',
    ];
}

test('each church sees only its own identity on the event page, ticket page, pdf, email, admin and ops', function () {
    PlatformSetting::set('deployment_mode', 'platform');
    [$a, $b] = two_churches();

    foreach ([[$a, $b], [$b, $a]] as [$own, $other]) {
        $surfaces = [
            'event page' => $this->get(route('public.event.show', [
                'org_slug' => $own['org']->slug,
                'event_slug' => $own['event']->slug,
            ])),
            'ticket page' => $this->get(route('public.tickets.show', ['order' => $own['order']->reference])),
            'email' => new TicketIssuedMail($own['order']->fresh(), 'pdf'),
        ];

        foreach ($surfaces as $surface => $output) {
            $html = $output instanceof TicketIssuedMail ? $output->render() : $output->assertOk()->getContent();

            expect($html)->toContain(e($own['org']->name));

            foreach (identity_of($other) as $marker) {
                expect($html)->not->toContain(e($marker));
            }
        }

        // The PDF is compressed, so assert on the data the pass template is rendered with.
        $rendered = null;
        View::composer('pdf.ticket-pass', static function ($view) use (&$rendered): void {
            $rendered = $view->getData();
        });
        app(TicketPassPdf::class)->render($own['order']->fresh())->output();
        expect($rendered['organization']->is($own['org']))
            ->toBeTrue()
            ->and($rendered['event']->is($own['event']))
            ->toBeTrue();
        $this->get(route('public.tickets.pdf', ['order' => $own['order']->reference]))->assertOk();

        // Staff surfaces resolve the signed-in admin's own organisation and event.
        foreach (['admin.index', 'admin.events', 'admin.attendees', 'ops.scanner', 'ops.cash-desk'] as $route) {
            $html = $this->actingAs($own['admin'])->get(route($route))->assertOk()->getContent();

            foreach (identity_of($other) as $marker) {
                expect($html)->not->toContain(e($marker));
            }
        }

        $this->actingAs($own['admin'])->get(route('admin.events'))->assertSee($own['event']->title);
        $this->actingAs($own['admin'])->get(route('ops.cash-desk'))->assertSee($own['event']->title);
        auth()->logout();
    }
});

test('in dedicated mode another church keeps its own links instead of the home church site', function () {
    [$home, $guest] = two_churches();
    PlatformSetting::set('deployment_mode', 'dedicated');
    PlatformSetting::set('home_org_slug', $home['org']->slug);

    $guestPages = [
        $this->get(route('public.event.show', [
            'org_slug' => $guest['org']->slug,
            'event_slug' => $guest['event']->slug,
        ])),
        $this->get(route('public.tenant.home', ['org_slug' => $guest['org']->slug])),
        $this->get(route('public.tenant.page', ['org_slug' => $guest['org']->slug, 'page' => 'about'])),
    ];

    foreach ($guestPages as $response) {
        $response
            ->assertOk()
            ->assertSee($guest['org']->name)
            ->assertDontSee($home['org']->name)
            ->assertSee(route('public.tenant.page', ['org_slug' => $guest['org']->slug, 'page' => 'about']), false)
            ->assertDontSee('href="' . route('dedicated.about') . '"', false)
            ->assertDontSee('href="' . url('/') . '"', false);
    }

    // The home church itself is still served from the root of the dedicated site.
    $this->get('/')->assertOk()->assertSee($home['org']->name)->assertSee(route('dedicated.about'), false);
});

test('a church without photos never gets stock or demo gallery images', function () {
    [$church] = two_churches();

    $this
        ->get(route('public.tenant.home', ['org_slug' => $church['org']->slug]))
        ->assertOk()
        ->assertDontSee('unsplash.com', false)
        ->assertDontSee('Perez Dome');

    $this
        ->get(route('public.tenant.page', ['org_slug' => $church['org']->slug, 'page' => 'gallery']))
        ->assertOk()
        ->assertSee('Photos coming soon')
        ->assertDontSee('unsplash.com', false)
        ->assertDontSee('Perez Dome');
});
