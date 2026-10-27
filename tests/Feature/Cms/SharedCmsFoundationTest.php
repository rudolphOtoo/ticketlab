<?php

declare(strict_types=1);

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventFaq;
use App\Models\EventFormField;
use App\Models\EventSchedule;
use App\Models\EventSpeaker;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use Illuminate\Support\Facades\Blade;

test('cms models belong to organization and event correctly', function (): void {
    $org1 = Organization::create([
        'name' => 'First Tech Hub',
        'slug' => 'first-tech-hub',
    ]);

    $org2 = Organization::create([
        'name' => 'Second Enterprise',
        'slug' => 'second-enterprise',
    ]);

    $event = Event::create([
        'organization_id' => $org1->id,
        'title' => 'Global Developers Summit 2026',
        'slug' => 'devsummit-2026',
        'venue' => 'Accra International Conference Centre',
        'start_at' => now()->addDays(10),
        'end_at' => now()->addDays(12),
        'status' => 'published',
        'customization' => [
            'theme_color' => '#b45309',
            'tagline' => 'Code, Scale & Transform',
        ],
    ]);

    $speaker = EventSpeaker::create([
        'organization_id' => $org1->id,
        'event_id' => $event->id,
        'name' => 'Dr. Kwame Mensah',
        'title_role' => 'Principal AI Architect',
        'topic' => 'Autonomous Agentic Systems in Production',
        'bio' => 'Pioneer in distributed neural state machines.',
        'sort_order' => 1,
        'is_featured' => true,
        'social_links' => ['x' => 'https://x.com/kwamemensah'],
    ]);

    $schedule = EventSchedule::create([
        'organization_id' => $org1->id,
        'event_id' => $event->id,
        'day_label' => 'Day 1',
        'session_title' => 'Opening Keynote: The Future of Agentic Software',
        'speaker_name' => 'Dr. Kwame Mensah',
        'venue_hall' => 'Grand Auditorium Hall A',
        'start_time' => '09:00',
        'end_time' => '10:30',
        'sort_order' => 1,
    ]);

    $faq = EventFaq::create([
        'organization_id' => $org1->id,
        'event_id' => $event->id,
        'question' => 'Is parking available on site?',
        'answer' => 'Yes, complimentary secured parking is available for all registered attendees.',
        'category' => 'logistics',
        'sort_order' => 1,
        'is_published' => true,
    ]);

    $formField = EventFormField::create([
        'organization_id' => $org1->id,
        'event_id' => $event->id,
        'field_key' => 'dietary_preference',
        'label' => 'Dietary Preference',
        'input_type' => 'select',
        'options' => ['Standard', 'Vegetarian', 'Vegan', 'Halal'],
        'is_required' => true,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Test Relationships
    expect($event->speakers)
        ->toHaveCount(1)
        ->and($event->speakers->first()->name)
        ->toBe('Dr. Kwame Mensah')
        ->and($event->schedules)
        ->toHaveCount(1)
        ->and($event->schedules->first()->session_title)
        ->toContain('Opening Keynote')
        ->and($event->faqs)
        ->toHaveCount(1)
        ->and($event->faqs->first()->question)
        ->toContain('parking')
        ->and($event->formFields)
        ->toHaveCount(1)
        ->and($event->formFields->first()->options)
        ->toBe(['Standard', 'Vegetarian', 'Vegan', 'Halal']);

    // Test Customization cast on event
    expect($event->customization)->toBeArray()->and($event->customization['theme_color'])->toBe('#b45309');
});

test('ticket types support perks and is_active flag', function (): void {
    $org = Organization::create([
        'name' => 'Perez Dome Events',
        'slug' => 'perez-dome-events',
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'National Believers Convention',
        'slug' => 'nbc-2026',
        'start_at' => now()->addDays(5),
        'end_at' => now()->addDays(7),
        'status' => 'published',
    ]);

    $vip = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP Executive Pass',
        'price' => 150.00,
        'total_capacity' => 100,
        'available_quantity' => 100,
        'perks' => ['Front Row Seating', 'Executive Lounge Access', 'Complimentary Materials'],
        'is_active' => true,
    ]);

    expect($vip->perks)->toBeArray()->and($vip->perks)->toHaveCount(3)->and($vip->is_active)->toBeTrue();
});

test('attendee supports dynamic custom_fields JSON storage', function (): void {
    $org = Organization::create([
        'name' => 'Summit Logistics',
        'slug' => 'summit-logistics',
    ]);

    $event = Event::create([
        'organization_id' => $org->id,
        'title' => 'Youth Leadership Summit',
        'slug' => 'yls-2026',
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(4),
        'status' => 'published',
    ]);

    $tier = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Delegate Pass',
        'price' => 50.00,
        'total_capacity' => 500,
        'available_quantity' => 500,
    ]);

    $order = Order::create([
        'organization_id' => $org->id,
        'reference' => 'ORD-TEST-999',
        'customer_name' => 'Kofi Boateng',
        'customer_email' => 'kofi@example.com',
        'customer_phone' => '0240000000',
        'gross_amount' => 50.00,
        'net_amount' => 50.00,
        'fee_amount' => 0.00,
        'payment_status' => \App\Enums\OrderStatus::Paid,
        'payment_method' => \App\Enums\PaymentMethod::PhysicalCash,
    ]);

    $attendee = Attendee::create([
        'order_id' => $order->id,
        'organization_id' => $org->id,
        'ticket_type_id' => $tier->id,
        'name' => 'Kofi Boateng',
        'email' => 'kofi@example.com',
        'phone' => '0240000000',
        'qr_code_hash' => hash('sha256', 'TEST-PASS-999'),
        'custom_fields' => [
            'bus_pickup' => 'Madina Zonal Station',
            'department' => 'Media & Protocol',
            'tshirt_size' => 'XL',
        ],
    ]);

    expect($attendee->custom_fields)
        ->toBeArray()
        ->and($attendee->custom_fields['bus_pickup'])
        ->toBe('Madina Zonal Station')
        ->and($attendee->custom_fields['department'])
        ->toBe('Media & Protocol');
});

test('sheaf ui skeleton, card, and modal components render cleanly', function (): void {
    $skeletonHtml = Blade::render('<x-ui.skeleton class="h-6 w-32" />');
    expect($skeletonHtml)->toContain('animate-pulse');

    $cardHtml = Blade::render('<x-ui.card title="Test Card" description="Card details">Content Here</x-ui.card>');
    expect($cardHtml)
        ->toContain('Test Card')
        ->and($cardHtml)
        ->toContain('Card details')
        ->and($cardHtml)
        ->toContain('Content Here');

    $modalHtml = Blade::render('<x-ui.modal name="test-modal" title="Modal Title">Modal Content</x-ui.modal>');
    expect($modalHtml)
        ->toContain('test-modal')
        ->and($modalHtml)
        ->toContain('Modal Title')
        ->and($modalHtml)
        ->toContain('Modal Content');
});
