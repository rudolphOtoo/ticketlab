<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TenantBespokeParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_tenant_org_slug_renders_bespoke_landing_showcase(): void
    {
        PlatformSetting::set('deployment_mode', 'platform');

        $org = Organization::factory()->create([
            'name' => 'Accra Gospel Outreach',
            'slug' => 'accra-gospel',
        ]);

        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'title' => 'Night of Power 2026',
            'status' => 'published',
            'is_public' => true,
        ]);

        $response = $this->get('/accra-gospel');

        $response->assertOk();
        $response->assertSee('Accra Gospel Outreach');
        $response->assertSee('Night of Power 2026');
    }

    public function test_multi_tenant_org_slug_uses_template_override_when_present(): void
    {
        PlatformSetting::set('deployment_mode', 'platform');

        $org = Organization::factory()->create([
            'name' => 'Custom Branded Org',
            'slug' => 'custom-override-org',
        ]);

        $overrideDir = resource_path('views/tenants/custom-override-org');
        $overrideFile = "{$overrideDir}/home.blade.php";

        File::ensureDirectoryExists($overrideDir);
        File::put($overrideFile, '<div id="custom-override-container">Custom Dedicated Override for {{ $organization->name }}</div>');

        try {
            $response = $this->get('/custom-override-org');

            $response->assertOk();
            $response->assertSee('custom-override-container', false);
            $response->assertSee('Custom Dedicated Override for Custom Branded Org');
        } finally {
            File::deleteDirectory($overrideDir);
        }
    }

    public function test_multi_tenant_org_bespoke_sub_pages_render_cleanly(): void
    {
        PlatformSetting::set('deployment_mode', 'platform');

        $org = Organization::factory()->create([
            'name' => 'Accra Gospel Outreach',
            'slug' => 'accra-gospel',
        ]);

        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'title' => 'Night of Power 2026',
            'status' => 'published',
            'is_public' => true,
        ]);

        $pages = ['about', 'speakers', 'packages', 'highlights', 'faq', 'contact'];

        foreach ($pages as $page) {
            $response = $this->get("/accra-gospel/page/{$page}");
            $response->assertOk();
            $response->assertSee('Accra Gospel Outreach');
        }
    }

    public function test_invalid_org_slug_returns_404(): void
    {
        PlatformSetting::set('deployment_mode', 'platform');

        $response = $this->get('/non-existent-tenant-slug-' . uniqid());

        $response->assertNotFound();
    }
}
