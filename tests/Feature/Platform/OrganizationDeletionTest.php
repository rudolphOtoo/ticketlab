<?php

declare(strict_types=1);

use App\Livewire\Platform\EditOrganization;
use App\Livewire\Platform\OnboardOrganization;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\RetiredOrganizationSlug;
use App\Models\SessionLedger;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Platform\OrganizationLifecycle;
use App\Support\MediaUrl;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * A super admin can delete only an organisation that never took an order or a payment, after typing its web address.
 * Its zones, users, empty events and Cloudinary folder go; its web address stays reserved, and the audit entry
 * survives it. Anything else is refused, pointing to Suspend or Archive.
 */

beforeEach(function (): void {
    $this->superAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $this->org = Organization::factory()->create(['slug' => 'grace', 'name' => 'Grace Chapel']);
    $this->zone = payable_zone($this->org, 'Zone A');
    $this->admin = User::factory()->create(['email' => 'admin@grace.test'])->assignMembership('admin', $this->org->id);
    $this->usher = User::factory()
        ->create(['email' => 'usher@grace.test'])
        ->assignMembership('usher', $this->org->id, $this->zone->id);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'slug' => 'retreat']);
    $this->tier = TicketType::factory()->create(['event_id' => $this->event->id]);
});

function delete_page(object $test): Testable
{
    return Livewire::actingAs($test->superAdmin)->test(EditOrganization::class, ['organization' => $test->org]);
}

it('deletes an organisation with no orders or payments once its web address is typed', function (): void {
    $other = Organization::factory()->create();
    $root = MediaUrl::organizationRoot($this->org->id);
    cloudinary()->seed("{$root}/logos/logo");
    cloudinary()->seed("{$root}/events/{$this->event->id}/banners/banner");
    cloudinary()->seed(MediaUrl::organizationRoot($other->id) . '/logos/logo');

    $page = delete_page($this)
        ->assertSee('It has no orders or payments.')
        ->set('deleteReason', 'Created by mistake during a demo.')
        ->set('deleteConfirmation', 'Grace')
        ->call('deleteOrganization')
        ->assertHasErrors(['deleteConfirmation' => 'in']);

    expect($this->org->fresh())->not->toBeNull();

    $page
        ->set('deleteConfirmation', 'grace')
        ->call('deleteOrganization')
        ->assertHasNoErrors()
        ->assertRedirect(route('platform.organizations'));

    expect(Organization::query()->whereKey($this->org->id)->exists())
        ->toBeFalse()
        ->and(Subdivision::query()->where('organization_id', $this->org->id)->exists())
        ->toBeFalse()
        ->and(Event::query()->whereKey($this->event->id)->exists())
        ->toBeFalse()
        ->and(TicketType::query()->whereKey($this->tier->id)->exists())
        ->toBeFalse()
        ->and(User::query()->whereIn('email', ['admin@grace.test', 'usher@grace.test'])->exists())
        ->toBeFalse()
        ->and(User::query()->whereKey($this->superAdmin->id)->exists())
        ->toBeTrue()
        // Its Cloudinary folder is gone; other organisations' images stay.
        ->and(cloudinary()->destroyedFolders)
        ->toBe([$root])
        ->and(array_keys(cloudinary()->images))
        ->toBe([MediaUrl::organizationRoot($other->id) . '/logos/logo']);

    // The audit entry outlives the organisation; its own audit trail went with it.
    $entry = PlatformAuditLog::query()->sole();
    expect($entry)
        ->action->toBe('organization.deleted')
        ->user_id->toBe($this->superAdmin->id)
        ->organization_id->toBe($this->org->id)
        ->organization_slug->toBe('grace')
        ->organization_name->toBe('Grace Chapel')
        ->reason->toBe('Created by mistake during a demo.')->and($entry->details)->toMatchArray([
            'events' => 1,
            'zones' => 1,
        ])->and($entry->details['users'])->toEqualCanonicalizing(['admin@grace.test', 'usher@grace.test'])->and(
            AuditLog::query()->where('organization_id', $this->org->id)->exists(),
        )->toBeFalse();

    $this->get('/grace')->assertNotFound();
});

it('needs a reason', function (): void {
    delete_page($this)
        ->set('deleteConfirmation', 'grace')
        ->call('deleteOrganization')
        ->assertHasErrors(['deleteReason' => 'required']);

    expect($this->org->fresh())->not->toBeNull();
});

it('refuses an organisation that took an order or a payment, pointing to Suspend and Archive', function (Closure $record): void {
    $record($this);

    delete_page($this)
        ->assertSeeHtml('data-delete-blocked')
        ->assertSee('Suspend it to take it offline, or archive it')
        ->set('deleteReason', 'Leaving the platform.')
        ->set('deleteConfirmation', 'grace')
        ->call('deleteOrganization')
        ->assertHasErrors(['deleteConfirmation']);

    expect($this->org->fresh())
        ->not
        ->toBeNull()
        ->and(RetiredOrganizationSlug::query()->exists())
        ->toBeFalse()
        ->and(PlatformAuditLog::query()->exists())
        ->toBeFalse()
        ->and(cloudinary()->destroyedFolders)
        ->toBe([]);
})->with([
    'a paid order with passes' => [static function (object $test): void {
        Attendee::factory()->create([
            'organization_id' => $test->org->id,
            'order_id' => Order::factory()
                ->paid()
                ->create(['organization_id' => $test->org->id, 'subdivision_id' => $test->zone->id])
                ->id,
            'subdivision_id' => $test->zone->id,
            'ticket_type_id' => $test->tier->id,
        ]);
    }],
    'an unpaid order' => [static function (object $test): void {
        Order::factory()->create(['organization_id' => $test->org->id, 'subdivision_id' => $test->zone->id]);
    }],
    'a cash session ledger' => [static function (object $test): void {
        SessionLedger::query()->create([
            'organization_id' => $test->org->id,
            'session_name' => 'Day 1',
            'opened_at' => now(),
            'total_cash' => 500,
        ]);
    }],
]);

it('refuses the dedicated site\'s home organisation', function (): void {
    PlatformSetting::set('deployment_mode', 'dedicated');
    PlatformSetting::set('home_org_slug', 'grace');

    expect(app(OrganizationLifecycle::class)->delete($this->org, $this->superAdmin, 'Cleaning up.'))
        ->toContain('home organisation')
        ->and($this->org->fresh())
        ->not->toBeNull();
});

it('keeps the web address reserved, so old links can never lead to another organisation', function (): void {
    app(OrganizationLifecycle::class)->delete($this->org, $this->superAdmin, 'Created by mistake.');

    Livewire::actingAs($this->superAdmin)
        ->test(OnboardOrganization::class)
        ->set('name', 'Grace Assembly')
        ->set('slug', 'grace')
        ->set('contactEmail', 'info@assembly.test')
        ->set('zones', [['name' => 'Accra', 'subaccount' => '']])
        ->set('adminName', 'New Admin')
        ->set('adminEmail', 'admin@assembly.test')
        ->call('save')
        ->assertHasErrors(['slug']);

    expect(Organization::query()->where('slug', 'grace')->exists())->toBeFalse();
});

it('is for super admins only', function (): void {
    $this->actingAs($this->admin)->get(route('platform.organizations.edit', $this->org))->assertForbidden();

    expect(fn() => app(OrganizationLifecycle::class)->delete($this->org, $this->admin, 'No.'))
        ->toThrow(InvalidArgumentException::class);
});
