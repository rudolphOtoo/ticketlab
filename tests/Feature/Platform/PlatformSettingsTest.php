<?php

declare(strict_types=1);

use App\Livewire\Admin\EventCustomizer;
use App\Livewire\Platform\PlatformDashboard;
use App\Livewire\Public\EventDirectory;
use App\Models\Event;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function (): void {
    // Reset platform settings in database and cache before each test
    PlatformSetting::query()->truncate();
    cache()->flush();
});

test('platform settings model caches and updates settings reliably', function (): void {
    expect(PlatformSetting::get('custom_key', 'fallback'))->toBe('fallback');

    PlatformSetting::set('custom_key', 'live_value');

    expect(PlatformSetting::get('custom_key'))->toBe('live_value');

    PlatformSetting::set('custom_key', 'updated_value');

    expect(PlatformSetting::get('custom_key'))->toBe('updated_value');

    PlatformSetting::forgetSetting('custom_key');

    expect(PlatformSetting::get('custom_key', 'default_again'))->toBe('default_again');
});

test('platform settings correctly reports dedicated mode state', function (): void {
    expect(PlatformSetting::isDedicatedMode())->toBeFalse();

    PlatformSetting::set('deployment_mode', 'dedicated');
    expect(PlatformSetting::isDedicatedMode())->toBeFalse(); // Needs home_org_slug

    PlatformSetting::set('home_org_slug', 'charis-ministries');
    expect(PlatformSetting::isDedicatedMode())->toBeTrue();
    expect(PlatformSetting::getHomeOrgSlug())->toBe('charis-ministries');

    PlatformSetting::set('deployment_mode', 'platform');
    expect(PlatformSetting::isDedicatedMode())->toBeFalse();
});

test('super admin can configure runtime deployment mode and home organization', function (): void {
    $superAdmin = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $org = Organization::factory()->create([
        'name' => 'Charis Global Ministries',
        'slug' => 'charis-ministries',
    ]);

    Livewire::actingAs($superAdmin)
        ->test(PlatformDashboard::class)
        ->assertSee('Runtime Deployment Personality')
        ->set('deploymentMode', 'dedicated')
        ->set('homeOrgSlug', 'charis-ministries')
        ->call('savePlatformSettings')
        ->assertHasNoErrors()
        ->assertSee('Runtime mode updated to Dedicated Bespoke Client');

    expect(PlatformSetting::get('deployment_mode'))->toBe('dedicated');
    expect(PlatformSetting::get('home_org_slug'))->toBe('charis-ministries');
    expect(PlatformSetting::isDedicatedMode())->toBeTrue();
});

test('non-existent organization is rejected when configuring dedicated mode', function (): void {
    $superAdmin = User::factory()->create([
        'role' => 'super_admin',
    ]);

    Livewire::actingAs($superAdmin)
        ->test(PlatformDashboard::class)
        ->set('deploymentMode', 'dedicated')
        ->set('homeOrgSlug', 'non-existent-organization-slug')
        ->call('savePlatformSettings')
        ->assertHasErrors(['homeOrgSlug']);
});

test('root route resolves public discovery hub in platform mode', function (): void {
    PlatformSetting::set('deployment_mode', 'platform');

    $response = $this->get('/');

    $response->assertOk();
    $response->assertViewIs('welcome');
    $response->assertSee('Where memorable gatherings begin');
});

test('root route resolves bespoke client landing in dedicated mode', function (): void {
    $org = Organization::factory()->create([
        'name' => 'Charis Ministries',
        'slug' => 'charis-ministries',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Kingdom Advancement Conference 2026',
        'slug' => 'kac-2026',
        'status' => 'published',
        'is_public' => true,
    ]);

    PlatformSetting::set('deployment_mode', 'dedicated');
    PlatformSetting::set('home_org_slug', 'charis-ministries');

    $response = $this->get('/');

    $response->assertOk();
    $response->assertViewIs('tenants.default-bespoke');
    $response->assertSee('Charis Ministries Presents');
    $response->assertSee('Kingdom Advancement Conference 2026');
});

test('root route resolves developer custom view if tenants slug home exists', function (): void {
    $org = Organization::factory()->create([
        'name' => 'Custom Client Co',
        'slug' => 'custom-client',
    ]);

    $customViewDir = resource_path('views/tenants/custom-client');
    File::ensureDirectoryExists($customViewDir);
    File::put(
        $customViewDir . '/home.blade.php',
        '<div id="developer-custom-tenant-view">Custom Tenant Portal for {{ $organization->name }}</div>',
    );

    try {
        PlatformSetting::set('deployment_mode', 'dedicated');
        PlatformSetting::set('home_org_slug', 'custom-client');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('developer-custom-tenant-view');
        $response->assertSee('Custom Tenant Portal for Custom Client Co');
    } finally {
        File::deleteDirectory($customViewDir);
    }
});

test('unlisted events are excluded from public event directory but accessible via direct slug', function (): void {
    $org = Organization::factory()->create();

    $publicEvent = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Open Public Summit',
        'slug' => 'open-public-summit',
        'status' => 'published',
        'is_public' => true,
    ]);

    $privateEvent = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'VIP Closed Door Gathering',
        'slug' => 'vip-closed-door-gathering',
        'status' => 'published',
        'is_public' => false,
    ]);

    // Test directory hides private event
    Livewire::test(EventDirectory::class)->assertSee('Open Public Summit')->assertDontSee('VIP Closed Door Gathering');

    // Test direct slug access works for private event
    $response = $this->get(route('public.event.show', [
        'org_slug' => $org->slug,
        'event_slug' => $privateEvent->slug,
    ]));

    $response->assertOk();
    $response->assertSee('VIP Closed Door Gathering');
});

test('event customizer omits visibility toggle in dedicated mode and displays it in platform mode', function (): void {
    $org = Organization::factory()->create();
    $admin = User::factory()->create(['organization_id' => $org->id]);

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Tech Expo',
        'is_public' => true,
    ]);

    // In platform mode, public directory listing toggle is visible
    PlatformSetting::set('deployment_mode', 'platform');
    Livewire::actingAs($admin)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->assertSee('Public Directory Listing');

    // In dedicated bespoke mode, public directory listing toggle is hidden
    PlatformSetting::set('deployment_mode', 'dedicated');
    PlatformSetting::set('home_org_slug', $org->slug);
    Livewire::actingAs($admin)
        ->test(EventCustomizer::class, ['eventId' => $event->id])
        ->assertDontSee('Public Directory Listing');
});
