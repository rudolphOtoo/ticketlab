<?php

declare(strict_types=1);

use App\Livewire\Admin\MarketingStudio;
use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

test('unauthenticated users are redirected from marketing studio', function (): void {
    $this->get(route('admin.studio.marketing'))->assertRedirect(route('login'));
});

test('authenticated admin without subdivision access is forbidden', function (): void {
    $user = User::factory()->create([
        'organization_id' => null,
        'role' => 'usher',
    ]);

    $this->actingAs($user)->get(route('admin.studio.marketing'))->assertForbidden();
});

test('authenticated user can access marketing studio and view flyer and qr controls', function (): void {
    $organization = Organization::factory()->create(['slug' => 'sheaf-studio']);
    $subdivision = Subdivision::factory()->create(['organization_id' => $organization->id]);
    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'subdivision_id' => $subdivision->id,
        'role' => 'admin',
    ]);

    $response = $this->actingAs($user)->get(route('admin.studio.marketing'));

    $response->assertOk();
    $response->assertSee('Marketing Studio');
    $response->assertSee('1:1');
    $response->assertSee('9:16');
    $response->assertSee('16:9');
    $response->assertSee('Export PNG');
    $response->assertSee('QR');
});

test('organization admin can view marketing studio with event data', function (): void {
    $org = Organization::factory()->create(['slug' => 'summit-org']);
    $subdivision = Subdivision::factory()->create(['organization_id' => $org->id]);
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'subdivision_id' => $subdivision->id,
        'role' => 'admin',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Global Leadership Summit 2026',
        'slug' => 'gls-2026',
        'status' => 'published',
        'start_at' => now()->addDays(14),
    ]);

    TicketType::factory()->create([
        'event_id' => $event->id,
        'name' => 'Regular Entry',
        'price' => 75.00,
    ]);

    $speaker = EventSpeaker::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'name' => 'Dr. Mensa Otabil',
        'title_role' => 'General Overseer',
        'topic' => 'The Power of Vision',
        'sort_order' => 1,
    ]);

    $this->actingAs($user);

    Livewire::test(MarketingStudio::class)
        ->assertOk()
        ->assertSee('Marketing Studio')
        ->assertSee('Global Leadership Summit 2026')
        ->assertSee('Dr. Mensa Otabil')
        ->assertSee('GHS 75.00')
        ->assertSet('selectedAspectRatio', '1:1')
        ->assertSet('featuredSpeakerId', $speaker->id);
});

test('marketing studio isolates events by organization tenancy', function (): void {
    $orgA = Organization::factory()->create(['slug' => 'org-a']);
    $subA = Subdivision::factory()->create(['organization_id' => $orgA->id]);
    $userA = User::factory()->create([
        'organization_id' => $orgA->id,
        'subdivision_id' => $subA->id,
        'role' => 'admin',
    ]);

    $eventA = Event::factory()->create([
        'organization_id' => $orgA->id,
        'title' => 'Alpha Conference',
        'slug' => 'alpha-conf',
    ]);

    $orgB = Organization::factory()->create(['slug' => 'org-b']);
    Event::factory()->create([
        'organization_id' => $orgB->id,
        'title' => 'Beta Secret Summit',
        'slug' => 'beta-secret',
    ]);

    $this->actingAs($userA);

    Livewire::test(MarketingStudio::class)
        ->assertOk()
        ->assertSee('Alpha Conference')
        ->assertDontSee('Beta Secret Summit');
});
