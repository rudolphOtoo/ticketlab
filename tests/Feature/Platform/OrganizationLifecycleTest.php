<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\OrganizationStatus;
use App\Enums\ReservationStatus;
use App\Jobs\SendEventReminders;
use App\Livewire\Admin\DataExportCentral;
use App\Livewire\Ops\CashDeskTerminal;
use App\Livewire\Platform\EditOrganization;
use App\Livewire\Platform\Organizations;
use App\Livewire\Platform\PlatformDashboard;
use App\Livewire\Public\EventDirectory;
use App\Livewire\Public\EventShow;
use App\Mail\TicketIssuedMail;
use App\Models\Attendee;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Platform\OrganizationLifecycle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * A platform super admin can suspend an organisation (public pages unavailable, no sales, its users can't sign in,
 * the scheduler skips it), archive it (the same, and hidden from the platform lists) and reactivate it. Nothing is
 * deleted, ticket links keep working, and every change is audited with who, when and why.
 */

beforeEach(function (): void {
    $this->superAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $this->org = Organization::factory()->create(['slug' => 'gmsu', 'name' => 'GMSU']);
    $this->zone = payable_zone($this->org, 'Zone A');
    $this->admin = User::factory()->create(['email' => 'admin@gmsu.test'])->assignMembership('admin', $this->org->id);
    $this->usher = User::factory()->create()->assignMembership('usher', $this->org->id);
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'title' => 'Convention 2027',
        'slug' => 'convention',
        'status' => 'published',
        'is_public' => true,
    ]);
    $this->tier = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'price' => 50,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);
    $this->order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $this->org->id,
            'subdivision_id' => $this->zone->id,
            'customer_name' => 'Kofi Mensah',
        ]);
    Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'order_id' => $this->order->id,
        'subdivision_id' => $this->zone->id,
        'ticket_type_id' => $this->tier->id,
        'name' => 'Kofi Mensah',
    ]);
});

function set_status(object $test, OrganizationStatus $status): void
{
    $lifecycle = app(OrganizationLifecycle::class);
    $organization = $test->org->fresh();

    match ($status) {
        OrganizationStatus::Active => $lifecycle->reactivate(
            $organization,
            $test->superAdmin,
            'Back in good standing.',
        ),
        OrganizationStatus::Suspended => $lifecycle->suspend($organization, $test->superAdmin, 'Unpaid platform fees.'),
        OrganizationStatus::Archived => $lifecycle->archive($organization, $test->superAdmin, 'Left the platform.'),
    };
}

it('is changed by a super admin with a reason, and audited', function (): void {
    $page = Livewire::actingAs($this->superAdmin)
        ->test(EditOrganization::class, ['organization' => $this->org])
        ->call('changeStatus', 'suspended')
        ->assertHasErrors(['statusReason' => 'required']);

    expect($this->org->fresh()->status)->toBe(OrganizationStatus::Active);

    $page
        ->set('statusReason', 'Unpaid platform fees.')
        ->call('changeStatus', 'suspended')
        ->assertHasNoErrors()
        ->assertSee('GMSU is suspended')
        ->set('statusReason', 'Fees settled.')
        ->call('changeStatus', 'active')
        ->assertSee('Suspended')
        ->assertSee('Unpaid platform fees.')
        ->assertSee('Reactivated')
        ->assertSee('Fees settled.');

    expect($this->org->fresh()->status)->toBe(OrganizationStatus::Active);

    $log = PlatformAuditLog::query()->orderBy('id')->get();
    expect($log->pluck('action')->all())
        ->toBe(['organization.suspended', 'organization.reactivated'])
        ->and($log->first())
        ->user_id->toBe($this->superAdmin->id)
        ->organization_id->toBe($this->org->id)
        ->organization_slug->toBe('gmsu')
        ->reason->toBe('Unpaid platform fees.')
        ->details->toBe(['from' => 'active', 'to' => 'suspended'])
        ->created_at->not->toBeNull();
});

it('can only be changed by a super admin', function (): void {
    $this->actingAs($this->admin)->get(route('platform.organizations.edit', $this->org))->assertForbidden();

    expect(fn() => app(OrganizationLifecycle::class)->suspend($this->org, $this->admin, 'No.'))
        ->toThrow(InvalidArgumentException::class);
});

describe('while suspended or archived', function (): void {
    it('shows "unavailable" on its public pages, keeps ticket links working, and lists nothing', function (
        OrganizationStatus $status,
        int $code,
    ): void {
        set_status($this, $status);

        foreach (['/gmsu', '/gmsu/convention', '/gmsu/page/about'] as $url) {
            $this->get($url)->assertStatus($code)->assertSee('GMSU is unavailable');
        }

        Livewire::test(EventDirectory::class)->assertDontSee('Convention 2027');
        $this->get('/')->assertOk()->assertDontSee('Convention 2027');

        $this->get(route('public.tickets.show', ['order' => $this->order->reference]))->assertOk();
        $this->get(route('public.tickets.pdf', ['order' => $this->order->reference]))->assertOk();
    })->with([
        'suspended' => [OrganizationStatus::Suspended, 503],
        'archived' => [OrganizationStatus::Archived, 410],
    ]);

    it('blocks checkout and the cash desk', function (): void {
        $page = Livewire::test(EventShow::class, ['org_slug' => 'gmsu', 'event_slug' => 'convention']);
        set_status($this, OrganizationStatus::Suspended);

        $page
            ->set('ticketTypeId', $this->tier->id)
            ->set('quantity', 1)
            ->set('customerName', 'Ama Owusu')
            ->set('customerEmail', 'ama@example.com')
            ->set('customerPhone', '0244123456')
            ->set('subdivisionId', $this->zone->id)
            ->checkoutAsShown()
            ->assertHasErrors(['payment' => 'Sales are closed because this organisation is unavailable.']);

        Livewire::actingAs($this->usher)
            ->test(CashDeskTerminal::class, ['eventId' => $this->event->id])
            ->call('selectTier', $this->tier->id)
            ->set('cashTendered', '50.00')
            ->set('saleZoneId', $this->zone->id)
            ->call('processCashSale')
            ->assertHasErrors(['tier']);

        expect(Reservation::query()->count())->toBe(0)->and(Attendee::query()->count())->toBe(1);
    });

    it('refuses its users at sign-in with a clear message', function (OrganizationStatus $status, string $word): void {
        set_status($this, $status);

        $this->followingRedirects()->post(route('login.store'), [
            'email' => 'admin@gmsu.test',
            'password' => 'password',
        ])->assertSee("Your organisation&#039;s TicketLab account {$word}", false);

        $this->assertGuest();
    })->with([
        'suspended' => [OrganizationStatus::Suspended, 'is suspended'],
        'archived' => [OrganizationStatus::Archived, 'has been archived'],
    ]);

    it('signs out users who were already signed in: scanner, cash desk and reports', function (string $route): void {
        set_status($this, OrganizationStatus::Suspended);
        $user = str_starts_with($route, 'ops.') ? $this->usher : $this->admin;

        $this
            ->actingAs($user)
            ->get(route($route))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => OrganizationStatus::Suspended->signInRefusal()]);

        $this->assertGuest();
    })->with(['ops.scanner', 'ops.cash-desk', 'admin.index', 'admin.exports']);

    it('answers a Livewire call from an open screen with the message', function (): void {
        set_status($this, OrganizationStatus::Suspended);

        $this->actingAs($this->usher)->withHeader('X-Livewire', '1')->get(route('ops.scanner'))->assertForbidden();

        $this->assertGuest();
    });

    it('never affects platform super admins', function (): void {
        set_status($this, OrganizationStatus::Suspended);

        $this->actingAs($this->superAdmin)->get(route('platform.organizations'))->assertOk();
        $this->assertAuthenticatedAs($this->superAdmin);
    });

    it('is skipped by the scheduler, which picks it up again once reactivated', function (): void {
        Mail::fake();
        Http::fake();
        set_status($this, OrganizationStatus::Suspended);
        $requestsBefore = count(Http::recorded());

        $this->order->forceFill(['ticket_email_due_at' => now()->subMinute()])->save();
        $hold = Reservation::factory()
            ->expired()
            ->create([
                'organization_id' => $this->org->id,
                'ticket_type_id' => $this->tier->id,
                'subdivision_id' => $this->zone->id,
            ]);
        Order::factory()->create([
            'organization_id' => $this->org->id,
            'subdivision_id' => $this->zone->id,
            'payment_status' => OrderStatus::Pending,
            'created_at' => now()->subHour(),
        ]);
        $this->zone->forceFill(['payout_checked_at' => null])->save();

        $this->artisan('tickets:email-pending')->expectsOutputToContain('No ticket emails owed.')->assertSuccessful();
        $this->artisan('reservations:release-expired')->expectsOutputToContain('Released 0')->assertSuccessful();
        $this->artisan('payments:reconcile-pending --execute')->assertSuccessful();
        $this->artisan('payouts:recheck-subaccounts')->assertSuccessful();

        // Neither Paystack's verify nor its subaccount check was called.
        expect(Http::recorded())->toHaveCount($requestsBefore);
        Mail::assertNothingSent();
        expect($hold->fresh()->status)->toBe(ReservationStatus::Active);

        set_status($this, OrganizationStatus::Active);

        $this->artisan('tickets:email-pending')->expectsOutputToContain('sent 1')->assertSuccessful();
        $this->artisan('reservations:release-expired')->expectsOutputToContain('Released 1')->assertSuccessful();
        Mail::assertSent(TicketIssuedMail::class, 1);
    });

    it('sends none of its queued broadcast emails', function (): void {
        Mail::fake();
        $broadcast = Broadcast::query()->create([
            'organization_id' => $this->org->id,
            'event_id' => $this->event->id,
            'subject' => 'Gates open at 8',
            'body' => 'See you tomorrow!',
            'recipients_count' => 1,
        ]);
        set_status($this, OrganizationStatus::Suspended);

        new SendEventReminders($broadcast->id, 'kofi@example.com', 'Kofi')->handle();

        Mail::assertNothingSent();
    });
});

it('keeps every record, so reports are whole again once reactivated', function (): void {
    set_status($this, OrganizationStatus::Suspended);
    set_status($this, OrganizationStatus::Active);

    $this->actingAs($this->admin)->get(route('admin.exports'))->assertOk();
    $response = Livewire::actingAs($this->admin)
        ->test(DataExportCentral::class, ['eventId' => $this->event->id])
        ->instance()
        ->exportAttendeeCsv();

    ob_start();
    $response->sendContent();

    expect(ob_get_clean())->toContain('Kofi Mensah');
    $this->get('/gmsu/convention')->assertOk();
});

it('hides archived organisations from the platform lists unless asked for', function (): void {
    Organization::factory()->create(['name' => 'Still Active Church']);
    set_status($this, OrganizationStatus::Archived);

    Livewire::actingAs($this->superAdmin)
        ->test(Organizations::class)
        ->assertSee('Still Active Church')
        ->assertDontSeeHtml('data-organization="gmsu"')
        ->assertSee('Show archived (1)')
        ->set('showArchived', true)
        ->assertSeeHtml('data-organization="gmsu"')
        ->assertSee('Archived');

    Livewire::actingAs($this->superAdmin)
        ->test(PlatformDashboard::class)
        ->assertSee('Still Active Church')
        ->assertDontSee('GMSU')
        ->set('showArchivedTenants', true)
        ->assertSee('GMSU');
});
