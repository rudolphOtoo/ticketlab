<?php

declare(strict_types=1);

use App\Livewire\Admin\ActiveEventSwitcher;
use App\Livewire\Admin\AttendeeRoster;
use App\Livewire\Admin\EventCustomizer;
use App\Livewire\Admin\EventManager;
use App\Livewire\Admin\TicketManager;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
});

test('admin creates an event with an auto-generated slug and uploaded banner', function (): void {
    Storage::fake('public');

    Livewire::actingAs($this->admin)
        ->test(EventManager::class)
        ->call('openCreateModal')
        ->set('title', 'Kingdom Summit 2027')
        ->assertSet('slug', 'kingdom-summit-2027')
        ->set('venue', 'Perez Dome')
        ->set('status', 'published')
        ->set('is_public', false)
        ->set('banner', UploadedFile::fake()->image('banner.jpg', 1600, 700))
        ->call('save')
        ->assertHasNoErrors();

    $event = Event::query()->where('slug', 'kingdom-summit-2027')->firstOrFail();

    expect($event)
        ->organization_id->toBe($this->org->id)
        ->status->toBe('published')
        ->is_public->toBeFalse()->and($event->banner_path)->toStartWith("events/{$this->org->id}/banners/");

    Storage::disk('public')->assertExists($event->banner_path);
    expect(session(ActiveEvent::SESSION_KEY))->toBe($event->id);
});

test('slugs are unique per organization but may repeat across tenants', function (): void {
    Event::factory()->create(['organization_id' => $this->org->id, 'slug' => 'summit']);
    Event::factory()->create(['slug' => 'other-tenant']);

    $component = Livewire::actingAs($this->admin)->test(EventManager::class)->call('openCreateModal');

    $component->set('title', 'Summit')->call('save')->assertHasErrors(['slug']);
    $component->set('slug', 'other-tenant')->call('save')->assertHasNoErrors();
});

test('end date must not precede the start date', function (): void {
    Livewire::actingAs($this->admin)
        ->test(EventManager::class)
        ->call('openCreateModal')
        ->set('title', 'Backwards')
        ->set('start_at', '2027-05-10T09:00')
        ->set('end_at', '2027-05-09T09:00')
        ->call('save')
        ->assertHasErrors(['end_at']);
});

test('events of another organization cannot be edited, activated or deleted', function (): void {
    $foreign = Event::factory()->create(['title' => 'Foreign Event']);

    Livewire::actingAs($this->admin)
        ->test(EventManager::class)
        ->assertDontSee('Foreign Event')
        ->call('editEvent', $foreign->id)
        ->assertSet('isModalOpen', false)
        ->call('activate', $foreign->id)
        ->call('deleteEvent', $foreign->id);

    expect($foreign->fresh())->not->toBeNull()->and(session(ActiveEvent::SESSION_KEY))->toBeNull();
});

test('events with issued passes cannot be deleted', function (): void {
    $event = Event::factory()->create(['organization_id' => $this->org->id]);
    $tier = TicketType::factory()->create(['event_id' => $event->id]);
    Attendee::factory()->create(['organization_id' => $this->org->id, 'ticket_type_id' => $tier->id]);

    Livewire::actingAs($this->admin)->test(EventManager::class)->call('deleteEvent', $event->id);

    expect($event->fresh())->not->toBeNull();
});

test('activating an event scopes the customizer, ticket manager and roster to it', function (): void {
    $older = Event::factory()->create(['organization_id' => $this->org->id, 'title' => 'Older Event']);
    $newer = Event::factory()->create(['organization_id' => $this->org->id, 'title' => 'Newer Event']);
    $olderTier = TicketType::factory()->create(['event_id' => $older->id]);
    $newerTier = TicketType::factory()->create(['event_id' => $newer->id]);
    Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $olderTier->id,
        'name' => 'Older Guest',
    ]);
    Attendee::factory()->create([
        'organization_id' => $this->org->id,
        'ticket_type_id' => $newerTier->id,
        'name' => 'Newer Guest',
    ]);

    // Without a selection the latest event is the working event.
    Livewire::actingAs($this->admin)->test(TicketManager::class)->assertSet('selectedEventId', $newer->id);

    Livewire::actingAs($this->admin)->test(EventManager::class)->call('activate', $older->id);

    expect(session(ActiveEvent::SESSION_KEY))->toBe($older->id);

    Livewire::actingAs($this->admin)->test(TicketManager::class)->assertSet('selectedEventId', $older->id);
    Livewire::actingAs($this->admin)->test(EventCustomizer::class)->assertSet('selectedEventId', $older->id);
    Livewire::actingAs($this->admin)
        ->test(AttendeeRoster::class)
        ->assertSee('Older Guest')
        ->assertDontSee('Newer Guest');
});

test('header switcher stores the chosen event and ignores foreign ids', function (): void {
    $mine = Event::factory()->create(['organization_id' => $this->org->id]);
    Event::factory()->create(['organization_id' => $this->org->id]);
    $foreign = Event::factory()->create();

    $switcher = Livewire::actingAs($this->admin)->test(ActiveEventSwitcher::class);

    $switcher->set('eventId', $foreign->id);
    expect(session(ActiveEvent::SESSION_KEY))->toBeNull();

    $switcher->set('eventId', $mine->id)->assertRedirect();
    expect(session(ActiveEvent::SESSION_KEY))->toBe($mine->id);
});

test('a stale session id from another tenant falls back to the latest own event', function (): void {
    $own = Event::factory()->create(['organization_id' => $this->org->id]);
    $foreign = Event::factory()->create();

    session([ActiveEvent::SESSION_KEY => $foreign->id]);

    expect(ActiveEvent::resolve($this->org->id)?->id)->toBe($own->id);
});
