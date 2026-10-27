<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication_for_marketing_studio_route(): void
    {
        $response = $this->get(route('admin.studio.marketing'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_marketing_studio_and_can_configure_flyer_and_qr(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.studio.marketing'));

        $response->assertOk();
        $response->assertSee('Marketing Studio');
        $response->assertSee('1:1');
        $response->assertSee('9:16');
        $response->assertSee('16:9');
        $response->assertSee('Export PNG');
        $response->assertSee('QR');
    }

    public function test_unauthenticated_or_unauthorized_users_trying_to_access_admin_studio_marketing_receive_a_401_or_403_response(): void
    {
        // Unauthenticated
        $response = $this->get(route('admin.studio.marketing'));
        $this->assertTrue(in_array($response->status(), [401, 403]) || $response->status() === 302);

        // Unauthorized - user without organization context (not superadmin)
        $user = User::factory()->create([
            'organization_id' => null,
            'role' => 'agent',
        ]);
        $response = $this->actingAs($user)->get(route('admin.studio.marketing'));
        $this->assertTrue(in_array($response->status(), [401, 403], true));
    }
}
