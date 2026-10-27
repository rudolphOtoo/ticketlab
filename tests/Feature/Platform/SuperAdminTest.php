<?php

declare(strict_types=1);

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use App\Livewire\Platform\PlatformDashboard;
use Livewire\Livewire;

test('unauthenticated users cannot access platform super admin console', function () {
    $this->get(route('platform.dashboard'))
        ->assertRedirect(route('login'));
});

test('non-super-admin users are rejected with 403 forbidden', function () {
    $user = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->actingAs($user)
        ->get(route('platform.dashboard'))
        ->assertForbidden();
});

test('super admin can access platform dashboard and view metrics', function () {
    $superAdmin = User::factory()->create([
        'role' => 'super_admin',
    ]);

    $org1 = Organization::factory()->create(['name' => 'Charis Ministries', 'slug' => 'charis-ministries']);
    $org2 = Organization::factory()->create(['name' => 'Accra Tech Hub', 'slug' => 'accra-tech-hub']);

    $event1 = Event::factory()->create([
        'organization_id' => $org1->id,
        'title' => 'Worship Explosion 2026',
        'status' => 'published',
    ]);
    $event2 = Event::factory()->create([
        'organization_id' => $org2->id,
        'title' => 'Vanguard Summit',
        'status' => 'published',
    ]);

    $tier = TicketType::factory()->create([
        'event_id' => $event1->id,
        'price' => 100.00,
    ]);

    $order = Order::factory()->create([
        'organization_id' => $org1->id,
        'payment_status' => \App\Enums\OrderStatus::Paid,
        'gross_amount' => 200.00,
    ]);

    Attendee::factory()->count(2)->create([
        'ticket_type_id' => $tier->id,
        'order_id' => $order->id,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('Cross-Tenant Oversight')
        ->assertSee('Charis Ministries')
        ->assertSee('Accra Tech Hub');

    Livewire::actingAs($superAdmin)
        ->test(PlatformDashboard::class)
        ->assertSee('Charis Ministries')
        ->assertSet('grossMerchandiseValue', 200.00)
        ->assertSet('platformFeeAccumulator', 6.00) // 2 tickets * 3.00
        ->call('viewTenant', $org1->id)
        ->assertSet('selectedTenantId', $org1->id)
        ->assertSee('Worship Explosion 2026')
        ->call('closeTenantModal')
        ->assertSet('selectedTenantId', null);
});
