<?php

declare(strict_types=1);

use App\Livewire\Admin\TicketManager;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('unauthenticated users cannot access ticket tier manager', function () {
    $this->get(route('admin.tickets'))->assertRedirect(route('login'));
});

test('authenticated admin can view ticket tier manager for their organization', function () {
    $org = Organization::create([
        'name' => 'Accra Summit Org',
        'slug' => 'accra-summit-' . uniqid(),
    ]);

    $user = User::forceCreate([
        'name' => 'Admin Jane',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
        'role' => 'admin',
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'DevFest West Africa 2026',
        'slug' => 'devfest-' . uniqid(),
        'venue' => 'Grand Arena',
        'start_at' => now(),
        'end_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Early Bird VIP',
        'description' => 'Full access with speaker lounge',
        'price' => 150.00,
        'total_capacity' => 100,
        'available_quantity' => 85,
        'is_active' => true,
        'perks' => ['Front Row', 'Buffet Lunch'],
    ]);

    $this
        ->actingAs($user)
        ->get(route('admin.tickets'))
        ->assertOk()
        ->assertSee('Early Bird VIP')
        ->assertSee('150.00')
        ->assertSee('Front Row');
});

test('org admin can create a new ticket tier with perks', function () {
    $org = Organization::create([
        'name' => 'Kumasi Expo Org',
        'slug' => 'kumasi-expo-' . uniqid(),
    ]);

    $user = User::forceCreate([
        'name' => 'Admin Kwame',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Tech Connect 2026',
        'slug' => 'tech-connect-' . uniqid(),
        'venue' => 'KNUST Great Hall',
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    Livewire::actingAs($user)
        ->test(TicketManager::class, ['eventId' => $event->id])
        ->call('openCreateModal')
        ->set('name', 'Executive All-Access')
        ->set('description', 'Access to keynotes and workshops')
        ->set('price', '250.00')
        ->set('total_capacity', 50)
        ->set('newPerkInput', 'Keynote Priority')
        ->call('addPerk')
        ->set('newPerkInput', 'Exclusive Swag Bag')
        ->call('addPerk')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('isModalOpen', false);

    expect(TicketType::query()->where('name', 'Executive All-Access')->exists())->toBeTrue();

    $created = TicketType::query()->where('name', 'Executive All-Access')->first();
    expect((float) $created->price)
        ->toBe(250.00)
        ->and($created->total_capacity)
        ->toBe(50)
        ->and($created->available_quantity)
        ->toBe(50)
        ->and($created->perks)
        ->toContain('Keynote Priority', 'Exclusive Swag Bag')
        ->and($created->is_active)
        ->toBeTrue();
});

test('org admin cannot reduce total capacity below sold tickets count', function () {
    $org = Organization::create([
        'name' => 'Summit Org',
        'slug' => 'summit-' . uniqid(),
    ]);

    $user = User::forceCreate([
        'name' => 'Admin Kofi',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Conference 2026',
        'slug' => 'conf-' . uniqid(),
        'venue' => 'Dome',
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Standard Pass',
        'price' => 50.00,
        'total_capacity' => 100,
        'available_quantity' => 60, // 40 tickets already sold!
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(TicketManager::class, ['eventId' => $event->id])
        ->call('editTicketType', $tier->id)
        ->set('total_capacity', 35) // Attempting to reduce below 40 sold
        ->call('save')
        ->assertHasErrors(['total_capacity']);

    // Now set capacity to 80 (valid increase or safe reduction above 40)
    Livewire::actingAs($user)
        ->test(TicketManager::class, ['eventId' => $event->id])
        ->call('editTicketType', $tier->id)
        ->set('total_capacity', 80)
        ->call('save')
        ->assertHasNoErrors();

    $tier->refresh();
    expect($tier->total_capacity)->toBe(80)->and($tier->available_quantity)->toBe(40); // 80 - 40 sold = 40 remaining
});

test('org admin can toggle active sales status', function () {
    $org = Organization::create([
        'name' => 'Expo Org',
        'slug' => 'expo-' . uniqid(),
    ]);

    $user = User::forceCreate([
        'name' => 'Admin Akosua',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Festival 2026',
        'slug' => 'festival-' . uniqid(),
        'venue' => 'Stadium',
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Flash Sale',
        'price' => 20.00,
        'total_capacity' => 200,
        'available_quantity' => 200,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)->test(TicketManager::class, ['eventId' => $event->id])->call('toggleActive', $tier->id);

    expect($tier->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($user)->test(TicketManager::class, ['eventId' => $event->id])->call('toggleActive', $tier->id);

    expect($tier->fresh()->is_active)->toBeTrue();
});

test('org admin can delete a tier with zero sales, but cannot delete a tier with linked attendees', function () {
    $org = Organization::create([
        'name' => 'Music Org',
        'slug' => 'music-' . uniqid(),
    ]);

    $user = User::forceCreate([
        'name' => 'Admin Yaw',
        'email' => 'admin_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $org->id,
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Live Music Concert',
        'slug' => 'music-concert-' . uniqid(),
        'venue' => 'Arena',
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    $unusedTier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Draft Unused Pass',
        'price' => 10.00,
        'total_capacity' => 50,
        'available_quantity' => 50,
        'is_active' => false,
    ]);

    $usedTier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Sold Out Pass',
        'price' => 80.00,
        'total_capacity' => 1,
        'available_quantity' => 0,
        'is_active' => true,
    ]);

    $order = Order::create([
        'organization_id' => $org->id,
        'reference' => 'TL-TEST-ORD-' . uniqid(),
        'customer_name' => 'John Doe',
        'customer_email' => 'john@ticketlab.test',
        'customer_phone' => '+233240000000',
        'gross_amount' => 80.00,
        'net_amount' => 80.00,
        'fee_amount' => 0.00,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    // Attach attendee to used tier
    Attendee::create([
        'organization_id' => $org->id,
        'order_id' => $order->id,
        'ticket_type_id' => $usedTier->id,
        'name' => 'John Doe',
        'email' => 'john@ticketlab.test',
        'phone' => '+233240000000',
        'qr_code_hash' => hash('sha256', 'payload-001'),
        'checked_in_at' => null,
    ]);

    // Deleting unused tier succeeds
    Livewire::actingAs($user)
        ->test(TicketManager::class, ['eventId' => $event->id])
        ->call('deleteTicketType', $unusedTier->id);

    expect(TicketType::query()->whereKey($unusedTier->id)->exists())->toBeFalse();

    // Deleting used tier is prevented
    Livewire::actingAs($user)
        ->test(TicketManager::class, ['eventId' => $event->id])
        ->call('deleteTicketType', $usedTier->id);

    expect(TicketType::query()->whereKey($usedTier->id)->exists())->toBeTrue();
});

test('multi-tenant isolation prevents admin from seeing or mutating another organization ticket tiers', function () {
    $orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a-' . uniqid()]);
    $orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b-' . uniqid()]);

    $adminA = User::forceCreate([
        'name' => 'Admin A',
        'email' => 'admin_a_' . uniqid() . '@ticketlab.test',
        'password' => Hash::make('password'),
        'organization_id' => $orgA->id,
        'role' => 'admin',
    ]);

    $eventB = Event::create([
        'organization_id' => $orgB->id,
        'title' => 'Secret Org B Event',
        'slug' => 'secret-b-' . uniqid(),
        'venue' => 'Classified',
        'start_at' => now(),
        'end_at' => now()->addDay(),
        'status' => 'published',
    ]);

    $tierB = TicketType::create([
        'event_id' => $eventB->id,
        'name' => 'Confidential Tier B',
        'price' => 500.00,
        'total_capacity' => 10,
        'available_quantity' => 10,
        'is_active' => true,
    ]);

    // Admin A visits ticket manager: cannot see Tier B
    $this->actingAs($adminA)->get(route('admin.tickets'))->assertOk()->assertDontSee('Confidential Tier B');

    // Admin A tries to edit or delete Tier B: fails silently or is ignored
    Livewire::actingAs($adminA)->test(TicketManager::class)->call('deleteTicketType', $tierB->id);

    expect(TicketType::query()->whereKey($tierB->id)->exists())->toBeTrue();
});
