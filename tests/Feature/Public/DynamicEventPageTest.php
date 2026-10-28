<?php

declare(strict_types=1);

use App\Livewire\Public\EventShow;
use App\Models\Event;
use App\Models\EventFaq;
use App\Models\EventFormField;
use App\Models\EventSchedule;
use App\Models\EventSpeaker;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Services\Payment\OrderFulfillmentService;
use Livewire\Livewire;

test('dynamic event page renders countdown, speakers, schedule, and faqs', function (): void {
    $org = Organization::factory()->create(['slug' => 'perez-chapel']);

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Breakthrough 2026',
        'slug' => 'breakthrough-2026',
        'status' => 'published',
        'start_at' => now()->addDays(5),
        'customization' => [
            'headline' => 'Supernatural Acceleration',
            'tagline' => 'A gathering for generational impact.',
            'show_countdown' => true,
            'show_speakers' => true,
            'show_schedule' => true,
            'show_faqs' => true,
        ],
    ]);

    TicketType::factory()->create([
        'event_id' => $event->id,
        'name' => 'Standard Access',
        'price' => 50.00,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    EventSpeaker::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'name' => 'Bishop Charles Agyinasare',
        'title_role' => 'Founder & Presiding Bishop',
        'topic' => 'Faith for Greater Works',
        'bio' => 'Serving the global body of Christ for over 40 years.',
        'sort_order' => 1,
    ]);

    EventSchedule::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'title' => 'Opening Plenary: Divine Encounter',
        'speaker_name' => 'Bishop Charles Agyinasare',
        'location' => 'Main Auditorium',
        'starts_at' => now()->addDays(5)->setHour(18)->setMinute(0),
        'sort_order' => 1,
    ]);

    EventFaq::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'question' => 'Is there bus transportation available?',
        'answer' => 'Yes, free pickup shuttles operate from Kwame Nkrumah Circle.',
        'sort_order' => 1,
    ]);

    Livewire::test(EventShow::class, ['org_slug' => $org->slug, 'event_slug' => $event->slug])
        ->assertSee('Supernatural Acceleration')
        ->assertSee('A gathering for generational impact')
        ->assertSee('Bishop Charles Agyinasare')
        ->assertSee('Faith for Greater Works')
        ->assertSee('Opening Plenary: Divine Encounter')
        ->assertSee('Main Auditorium')
        ->assertSee('Is there bus transportation available?')
        ->assertSee('free pickup shuttles operate');
});

test('attendee can open and view speaker bio modal', function (): void {
    $org = Organization::factory()->create(['slug' => 'tech-accra']);

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Accra Tech Summit',
        'slug' => 'tech-summit',
        'status' => 'published',
    ]);

    TicketType::factory()->create([
        'event_id' => $event->id,
        'price' => 20.00,
    ]);

    $speaker = EventSpeaker::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'name' => 'Ama Serwaa',
        'title' => 'Principal AI Researcher',
        'bio' => 'Pioneering African NLP language models and neuro-symbolic reasoning.',
        'sort_order' => 1,
    ]);

    Livewire::test(EventShow::class, ['org_slug' => $org->slug, 'event_slug' => $event->slug])
        ->call('viewSpeaker', $speaker->id)
        ->assertSee('Pioneering African NLP language models')
        ->call('closeSpeakerModal')
        ->assertDontSee('Pioneering African NLP language models');
});

test('dynamic questionnaire validates required fields during checkout', function (): void {
    $org = Organization::factory()->create(['slug' => 'summit-hub']);

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Leadership Intensive',
        'slug' => 'leadership-intensive',
        'status' => 'published',
    ]);

    $ticket = TicketType::factory()->create([
        'event_id' => $event->id,
        'price' => 50.00,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    EventFormField::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'field_key' => 't_shirt_size',
        'label' => 'T-Shirt Size',
        'field_type' => 'select',
        'options' => ['Small', 'Medium', 'Large', 'XL'],
        'is_required' => true,
        'sort_order' => 1,
    ]);

    EventFormField::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'field_key' => 'dietary_restrictions',
        'label' => 'Dietary Restrictions',
        'field_type' => 'text',
        'is_required' => false,
        'sort_order' => 2,
    ]);

    // Validation fails when required t_shirt_size is missing
    Livewire::test(EventShow::class, ['org_slug' => $org->slug, 'event_slug' => $event->slug])
        ->set('ticketTypeId', $ticket->id)
        ->set('quantity', 1)
        ->set('customerName', 'Kwesi Arthur')
        ->set('customerEmail', 'kwesi@example.com')
        ->set('customerPhone', '0244112233')
        ->call('startCheckout')
        ->assertHasErrors(['customFieldResponses.t_shirt_size']);

    // Validation fails when t_shirt_size has invalid option
    Livewire::test(EventShow::class, ['org_slug' => $org->slug, 'event_slug' => $event->slug])
        ->set('ticketTypeId', $ticket->id)
        ->set('quantity', 1)
        ->set('customerName', 'Kwesi Arthur')
        ->set('customerEmail', 'kwesi@example.com')
        ->set('customerPhone', '0244112233')
        ->set('customFieldResponses.t_shirt_size', 'NonExistentSize')
        ->call('startCheckout')
        ->assertHasErrors(['customFieldResponses.t_shirt_size']);
});

test('dynamic questionnaire responses are persisted to attendees custom_fields upon order fulfillment', function (): void {
    $org = Organization::factory()->create(['slug' => 'summit-hub']);

    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Leadership Intensive',
        'slug' => 'leadership-intensive',
        'status' => 'published',
    ]);

    $ticket = TicketType::factory()->create([
        'event_id' => $event->id,
        'price' => 50.00,
        'total_capacity' => 100,
        'available_quantity' => 100,
    ]);

    EventFormField::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'field_key' => 't_shirt_size',
        'label' => 'T-Shirt Size',
        'field_type' => 'select',
        'options' => ['Small', 'Medium', 'Large', 'XL'],
        'is_required' => true,
        'sort_order' => 1,
    ]);

    EventFormField::create([
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'field_key' => 'department',
        'label' => 'Ministry / Department',
        'field_type' => 'text',
        'is_required' => false,
        'sort_order' => 2,
    ]);

    $component = Livewire::test(EventShow::class, ['org_slug' => $org->slug, 'event_slug' => $event->slug])
        ->set('ticketTypeId', $ticket->id)
        ->set('quantity', 1)
        ->set('customerName', 'Kofi Boateng')
        ->set('customerEmail', 'kofi@example.com')
        ->set('customerPhone', '0244998877')
        ->set('customFieldResponses.t_shirt_size', 'Large')
        ->set('customFieldResponses.department', 'Media & IT')
        ->call('startCheckout')
        ->assertHasNoErrors();

    $orderRef = $component->get('orderReference');
    expect($orderRef)->not->toBeNull();

    $order = Order::query()->where('reference', $orderRef)->first();
    expect($order)->not->toBeNull();
    expect($order->metadata['custom_fields'])->toHaveKeys(['t_shirt_size', 'department']);
    expect($order->metadata['custom_fields']['t_shirt_size']['value'])->toBe('Large');
    expect($order->metadata['custom_fields']['department']['value'])->toBe('Media & IT');

    // Fulfill order and verify attendee custom_fields
    $fulfillment = app(OrderFulfillmentService::class);
    $paymentPayload = [
        'reference' => $order->reference,
        'status' => 'success',
        'amount' => $order->gross_pesewas,
        'currency' => 'GHS',
        'channel' => 'mobile_money',
    ];

    $fulfilledOrder = $fulfillment->fulfill($order, $paymentPayload);
    expect($fulfilledOrder->attendees)->toHaveCount(1);

    $attendee = $fulfilledOrder->attendees->first();
    expect($attendee->custom_fields)->not->toBeNull();
    expect($attendee->custom_fields['t_shirt_size']['value'])->toBe('Large');
    expect($attendee->custom_fields['department']['value'])->toBe('Media & IT');
});
