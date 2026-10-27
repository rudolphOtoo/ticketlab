<?php

declare(strict_types=1);

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventFormField;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use App\Livewire\Admin\DataExportCentral;
use Livewire\Livewire;

test('org admin can access data export central', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'role' => 'org_admin',
    ]);

    $this->actingAs($user)
        ->get(route('admin.exports'))
        ->assertOk()
        ->assertSee('Data Export &amp; Sovereignty Central', false)
        ->assertSee('Zero Vendor Lock-In');
});

test('attendee csv export streams rows and expands dynamic custom form fields', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'role' => 'org_admin',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Leadership Summit 2026',
        'status' => 'published',
    ]);

    $tier = TicketType::factory()->create([
        'event_id' => $event->id,
        'name' => 'VIP Delegate',
        'price' => 150.00,
    ]);

    $formField = EventFormField::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'label' => 'Bus Route Pickup',
        'field_key' => 'bus_route_pickup',
        'input_type' => 'text',
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $order = Order::factory()->create([
        'organization_id' => $org->id,
    ]);

    Attendee::factory()->create([
        'ticket_type_id' => $tier->id,
        'order_id' => $order->id,
        'name' => 'Kofi Mensah',
        'email' => 'kofi@example.com',
        'custom_fields' => [
            'bus_route_pickup' => 'Madina Station Stop 3',
        ],
    ]);

    $component = Livewire::actingAs($user)
        ->test(DataExportCentral::class, ['eventId' => $event->id]);

    $response = $component->instance()->exportAttendeeCsv();

    expect($response->headers->get('content-type'))->toBe('text/csv');

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Kofi Mensah')
        ->toContain('kofi@example.com')
        ->toContain('VIP Delegate')
        ->toContain('Bus Route Pickup')
        ->toContain('Madina Station Stop 3');
});

test('json backup export generates complete schema footprint of the tenant', function () {
    $org = Organization::factory()->create([
        'name' => 'Grace Assembly',
        'slug' => 'grace-assembly',
    ]);
    $user = User::factory()->create([
        'organization_id' => $org->id,
        'role' => 'org_admin',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Annual Convocation',
        'slug' => 'annual-convocation',
        'status' => 'published',
    ]);

    TicketType::factory()->create([
        'event_id' => $event->id,
        'name' => 'Standard Pass',
        'price' => 50.00,
    ]);

    $component = Livewire::actingAs($user)
        ->test(DataExportCentral::class);

    $response = $component->instance()->exportJsonBackup();

    expect($response->headers->get('content-type'))->toBe('application/json');

    ob_start();
    $response->sendContent();
    $rawJson = ob_get_clean();

    $data = json_decode($rawJson, true);

    expect($data)->toBeArray()
        ->toHaveKey('generator')
        ->toHaveKey('organization')
        ->toHaveKey('events');

    expect($data['organization']['name'])->toBe('Grace Assembly');
    expect($data['events'][0]['title'])->toBe('Annual Convocation');
    expect($data['events'][0]['ticket_types'][0]['name'])->toBe('Standard Pass');
});
