<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\BespokeStudio;
use App\Models\Event;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BespokeStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_can_access_bespoke_studio(): void
    {
        $org = Organization::factory()->create([
            'name' => 'Kingdom Network',
            'slug' => 'kingdom-network',
        ]);

        $admin = User::factory()->create([
            'organization_id' => $org->id,
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.studio.bespoke'))
            ->assertOk()
            ->assertSee('Bespoke Portal CMS')
            ->assertSee('Navigation Flow');
    }

    public function test_admin_can_save_navigation_flow_and_toggle_pages(): void
    {
        $org = Organization::factory()->create([
            'name' => 'Kingdom Network',
            'slug' => 'kingdom-network',
        ]);

        $admin = User::factory()->create([
            'organization_id' => $org->id,
            'role' => 'admin',
        ]);

        Livewire::actingAs($admin)
            ->test(BespokeStudio::class)
            ->set('nav_about_label', 'Our Story')
            ->set('nav_partnership_enabled', true)
            ->set('nav_partnership_label', 'Sponsors')
            ->call('saveNavigation')
            ->assertHasNoErrors()
            ->assertSet('flashMessage', 'Bespoke navigation settings saved successfully.');

        $org->refresh();
        $nav = $org->settings['bespoke']['nav'] ?? [];

        $this->assertSame('Our Story', $nav['about']['label']);
        $this->assertTrue($nav['partnership']['enabled']);
        $this->assertSame('Sponsors', $nav['partnership']['label']);

        // Check bespokeNavLinks output
        $links = $org->bespokeNavLinks(true);
        $labels = array_column($links, 'label');
        $this->assertContains('Our Story', $labels);
        $this->assertContains('Sponsors', $labels);
    }

    public function test_admin_can_save_about_page_and_it_reflects_on_public_tenant_page(): void
    {
        PlatformSetting::set('deployment_mode', 'platform');

        $org = Organization::factory()->create([
            'name' => 'Kingdom Network',
            'slug' => 'kingdom-network',
        ]);

        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'title' => 'Kingdom Summit 2026',
            'status' => 'published',
            'is_public' => true,
        ]);

        $admin = User::factory()->create([
            'organization_id' => $org->id,
            'role' => 'admin',
        ]);

        Livewire::actingAs($admin)
            ->test(BespokeStudio::class)
            ->set('about_heading', 'The Kingdom Journey')
            ->set('about_lead', 'Transforming Cities Through Faith')
            ->set('about_story', 'Founded in 2020, we empower visionary leaders worldwide.')
            ->call('saveAboutPage')
            ->assertHasNoErrors();

        $response = $this->get('/kingdom-network/page/about');
        $response->assertOk();
        $response->assertSee('The Kingdom Journey');
        $response->assertSee('Transforming Cities Through Faith');
        $response->assertSee('Founded in 2020, we empower visionary leaders worldwide.');
    }

    public function test_admin_can_save_highlights_page_with_custom_stats(): void
    {
        PlatformSetting::set('deployment_mode', 'platform');

        $org = Organization::factory()->create([
            'name' => 'Kingdom Network',
            'slug' => 'kingdom-network',
        ]);

        Event::factory()->create([
            'organization_id' => $org->id,
            'title' => 'Kingdom Summit 2026',
            'status' => 'published',
            'is_public' => true,
        ]);

        $admin = User::factory()->create([
            'organization_id' => $org->id,
            'role' => 'admin',
        ]);

        Livewire::actingAs($admin)
            ->test(BespokeStudio::class)
            ->set('highlights_heading', 'Summit By The Numbers')
            ->set('stat1_value', '5,000+')
            ->set('stat1_label', 'Global Delegates')
            ->set('stat2_value', '48')
            ->set('stat2_label', 'Breakout Workshops')
            ->call('saveHighlightsPage')
            ->assertHasNoErrors();

        $response = $this->get('/kingdom-network/page/highlights');
        $response->assertOk();
        $response->assertSee('Summit By The Numbers');
        $response->assertSee('5,000+');
        $response->assertSee('Global Delegates');
        $response->assertSee('48');
        $response->assertSee('Breakout Workshops');
    }

    public function test_ticketing_page_renders_collapsible_bespoke_navbar(): void
    {
        PlatformSetting::set('deployment_mode', 'platform');

        $org = Organization::factory()->create([
            'name' => 'Summit Organization',
            'slug' => 'summit-org',
        ]);

        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'title' => 'Summit 2026',
            'slug' => 'summit-2026',
            'status' => 'published',
            'is_public' => true,
        ]);

        TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'Standard Pass',
            'price' => 50.00,
            'available_quantity' => 100,
        ]);

        $response = $this->get('/summit-org/summit-2026');

        $response->assertOk();
        // Organization brand badge in navbar
        $response->assertSee('Summit Organization');
        // Navigation links to tenant subpages
        $response->assertSee('/summit-org/page/about');
        $response->assertSee('/summit-org/page/speakers');
        // Collapsible trigger micro-interaction
        $response->assertSee('Minimize Navigation');
        $response->assertSee('toggleCollapse()', false);
    }
}

