<?php

declare(strict_types=1);

use App\Livewire\Platform\EditOrganization;
use App\Livewire\Platform\PlatformDashboard;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use App\Services\Payment\PaystackSubaccountResolver;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->superAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $this->org = Organization::factory()->create([
        'name' => 'Ghana Methodist Student Union',
        'slug' => 'gmsu',
        'subdivision_label' => 'Subdivision',
        'settings' => ['contact_email' => 'old@example.test', 'featured_event_id' => 9],
    ]);
});

test('a super admin edits the organisation identity; the web address and other settings are untouched', function (): void {
    Livewire::actingAs($this->superAdmin)
        ->test(EditOrganization::class, ['organization' => $this->org])
        ->assertSet('contactEmail', 'old@example.test')
        ->set('name', 'Ghana Methodist Students Union')
        ->set('subdivisionLabel', 'Zone')
        ->set('contactEmail', 'info@gmsu.example.test')
        ->set('logoUrl', 'http://insecure.example.test/logo.png')
        ->call('save')
        ->assertHasErrors('logoUrl')
        ->set('logoUrl', 'https://cdn.example.test/gmsu.png')
        ->set('brandColor', '#1d4ed8')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('was updated');

    $org = $this->org->fresh();
    expect($org->name)
        ->toBe('Ghana Methodist Students Union')
        ->and($org->slug)
        ->toBe('gmsu')
        ->and($org->subdivision_label)
        ->toBe('Zone')
        ->and($org->logo_url)
        ->toBe('https://cdn.example.test/gmsu.png')
        ->and($org->settings)
        ->toMatchArray([
            'contact_email' => 'info@gmsu.example.test',
            'brand_color' => '#1d4ed8',
            'featured_event_id' => 9,
        ])
        ->and(AuditLog::query()->where('action', 'organization.updated')->sole()->user_id)
        ->toBe($this->superAdmin->id);
});

test('a super admin adds zones; a subaccount code is verified with Paystack and stored as verified', function (): void {
    Http::fake([
        'api.paystack.co/subaccount/ACCT_capecoast1' => Http::response([
            'status' => true,
            'data' => ['active' => true, 'currency' => 'GHS'],
        ]),
        'api.paystack.co/subaccount/*' => Http::response(['status' => false, 'message' => 'Subaccount not found'], 404),
    ]);
    Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Accra', 'slug' => 'accra']);

    $page = Livewire::actingAs($this->superAdmin)->test(EditOrganization::class, ['organization' => $this->org]);

    // Duplicate names are refused ignoring case; a different name whose slug collides gets a suffixed slug.
    $page->set('newZoneName', ' accra ')->call('addZone')->assertHasErrors('newZoneName');
    $page->set('newZoneName', 'Accra!')->call('addZone')->assertHasNoErrors();
    $page
        ->set('newZoneName', 'Kumasi')
        ->set('newZoneSubaccount', 'ACCT_typo')
        ->call('addZone')
        ->assertHasErrors('newZoneSubaccount');
    $page
        ->set('newZoneName', 'Cape Coast')
        ->set('newZoneSubaccount', 'ACCT_capecoast1')
        ->call('addZone')
        ->assertHasNoErrors();

    $capeCoast = Subdivision::query()->where('name', 'Cape Coast')->sole();
    expect(app(PaystackSubaccountResolver::class)->forSubdivision($capeCoast))
        ->toBe('ACCT_capecoast1')
        ->and(Subdivision::query()->where('name', 'Kumasi')->exists())
        ->toBeFalse()
        ->and(
            Subdivision::query()
                ->where('organization_id', $this->org->id)
                ->pluck('slug')
                ->sort()
                ->values()
                ->all(),
        )
        ->toBe(['accra', 'accra-2', 'cape-coast']);
});

test('only the super admin can open the edit page', function (): void {
    $orgAdmin = User::factory()->create()->assignMembership('admin', $this->org->id);

    $this->actingAs($orgAdmin)->get(route('platform.organizations.edit', $this->org))->assertForbidden();
    $this
        ->actingAs($this->superAdmin)
        ->get(route('platform.organizations.edit', $this->org))
        ->assertOk()
        ->assertSee('/gmsu');
});

test('the dashboard links to the edit page instead of the old jump-to-admin button', function (): void {
    Livewire::actingAs($this->superAdmin)
        ->test(PlatformDashboard::class)
        ->assertDontSee('jumpToTenantAdmin')
        ->assertSee(route('platform.organizations.edit', $this->org), false);

    expect(method_exists(PlatformDashboard::class, 'jumpToTenantAdmin'))->toBeFalse();
});
