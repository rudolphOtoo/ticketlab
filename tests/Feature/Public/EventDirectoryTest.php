<?php

declare(strict_types=1);

use App\Livewire\Public\EventDirectory;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use Livewire\Livewire;

test('public events directory page renders successfully', function () {
    $this->get(route('public.events.index'))->assertOk()->assertSee('Explore Upcoming Events');
});

test('public events directory lists published events', function () {
    $org = Organization::factory()->create();
    $event = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Accra Gospel Summit',
        'status' => 'published',
        'venue' => 'National Theatre',
    ]);

    Livewire::test(EventDirectory::class)->assertSee('Accra Gospel Summit')->assertSee('National Theatre');
});

test('public events directory filters events by search query', function () {
    $org = Organization::factory()->create();
    $event1 = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Design & Code Meetup',
        'status' => 'published',
    ]);
    $event2 = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'AgriTech Conference',
        'status' => 'published',
    ]);

    Livewire::test(EventDirectory::class)
        ->set('search', 'Design')
        ->assertSee('Design & Code Meetup')
        ->assertDontSee('AgriTech Conference');
});

test('public events directory filters by free passes', function () {
    $org = Organization::factory()->create();
    $freeEvent = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Free Community Workshop',
        'status' => 'published',
    ]);
    TicketType::factory()->create([
        'event_id' => $freeEvent->id,
        'price' => 0.00,
    ]);

    $paidEvent = Event::factory()->create([
        'organization_id' => $org->id,
        'title' => 'Executive Masterclass',
        'status' => 'published',
    ]);
    TicketType::factory()->create([
        'event_id' => $paidEvent->id,
        'price' => 150.00,
    ]);

    Livewire::test(EventDirectory::class)
        ->set('filter', 'free')
        ->assertSee('Free Community Workshop')
        ->assertDontSee('Executive Masterclass');
});
