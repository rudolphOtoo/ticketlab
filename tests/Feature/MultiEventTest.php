<?php

declare(strict_types=1);

use App\Livewire\Admin\BespokeStudio;
use App\Livewire\Admin\EventCustomizer;
use App\Livewire\Admin\EventManager;
use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Organization;
use App\Models\User;
use App\Support\ActiveEvent;
use App\Support\DedicatedSite;
use App\Support\EventGallery;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->org = Organization::factory()->create(['slug' => 'summit-org', 'name' => 'Summit Org']);
    $this->admin = User::factory()->create()->assignMembership('admin', $this->org->id);
});

function create_event_as(User $admin, string $title): Event
{
    Livewire::actingAs($admin)
        ->test(EventManager::class)
        ->call('openCreateModal')
        ->set('title', $title)
        ->set('status', 'published')
        ->call('save')
        ->assertHasNoErrors();

    return Event::query()->where('title', $title)->sole();
}

/*
 |--------------------------------------------------------------------------
 | Issue A: several published events coexist
 |--------------------------------------------------------------------------
 */

test('creating a second event keeps the admin on the event they were working on', function (): void {
    $first = create_event_as($this->admin, 'Summit 2026');

    expect(session(ActiveEvent::SESSION_KEY))->toBe($first->id);

    create_event_as($this->admin, 'Summit 2027');

    expect(session(ActiveEvent::SESSION_KEY))->toBe($first->id)->and(ActiveEvent::id($this->org->id))->toBe($first->id);
});

test('an admin who never picked an event is not moved by a new one', function (): void {
    $existing = Event::factory()->create(['organization_id' => $this->org->id]);

    // Visiting any admin screen resolves the working event; it is then pinned.
    $this->actingAs($this->admin)->get(route('admin.events'))->assertOk();

    create_event_as($this->admin, 'Brand New Event');

    expect(ActiveEvent::id($this->org->id))->toBe($existing->id);
});

test('every published event stays reachable on its own public page', function (): void {
    $first = create_event_as($this->admin, 'Summit 2026');
    $second = create_event_as($this->admin, 'Summit 2027');

    foreach ([$first, $second] as $event) {
        $this
            ->get(route('public.event.show', ['org_slug' => 'summit-org', 'event_slug' => $event->slug]))
            ->assertOk()
            ->assertSee($event->title);
    }

    expect($first->fresh()->status)->toBe('published');
});

test('the organisation landing page lists every published event but never drafts', function (): void {
    $upcoming = Event::factory()->create([
        'organization_id' => $this->org->id,
        'title' => 'Upcoming Summit',
        'start_at' => now()->addWeek(),
        'end_at' => now()->addWeek()->addDay(),
    ]);
    $later = Event::factory()->create([
        'organization_id' => $this->org->id,
        'title' => 'Later Gala',
        'start_at' => now()->addMonths(2),
        'end_at' => now()->addMonths(2)->addDay(),
    ]);
    Event::factory()->create([
        'organization_id' => $this->org->id,
        'title' => 'Secret Draft',
        'status' => 'draft',
        'start_at' => now()->addDay(),
    ]);

    $site = DedicatedSite::forOrganization($this->org);

    expect($site->event?->id)
        ->toBe($upcoming->id)
        ->and($site->events->pluck('id')->all())
        ->toBe([$upcoming->id, $later->id]);

    $this
        ->get(route('public.tenant.home', ['org_slug' => 'summit-org']))
        ->assertOk()
        ->assertSee('Upcoming Summit')
        ->assertSee('Later Gala')
        ->assertSee(route('public.event.show', ['org_slug' => 'summit-org', 'event_slug' => $later->slug]))
        ->assertDontSee('Secret Draft');
});

test('an organisation with only drafts shows no event publicly', function (): void {
    Event::factory()->create(['organization_id' => $this->org->id, 'title' => 'Unannounced', 'status' => 'draft']);

    expect(DedicatedSite::forOrganization($this->org)->event)->toBeNull();
});

test('admins choose which published event headlines the public site', function (): void {
    $soonest = Event::factory()->create([
        'organization_id' => $this->org->id,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
    ]);
    $flagship = Event::factory()->create([
        'organization_id' => $this->org->id,
        'start_at' => now()->addMonth(),
        'end_at' => now()->addMonth()->addDay(),
    ]);
    $draft = Event::factory()->create(['organization_id' => $this->org->id, 'status' => 'draft']);

    expect(DedicatedSite::forOrganization($this->org)->event?->id)->toBe($soonest->id);

    Livewire::actingAs($this->admin)->test(EventManager::class)->call('featureEvent', $draft->id);
    Livewire::actingAs($this->admin)
        ->test(EventManager::class)
        ->call('featureEvent', $flagship->id)
        ->assertSee('Featured on site');

    $site = DedicatedSite::forOrganization($this->org->fresh());

    expect($site->event?->id)
        ->toBe($flagship->id)
        ->and($site->events->pluck('id')->all())
        ->toBe([$flagship->id, $soonest->id]);
});

/*
 |--------------------------------------------------------------------------
 | Issue B: media belongs to an event
 |--------------------------------------------------------------------------
 */

test('banners and speaker photos are filed under the event that owns them', function (): void {
    $first = create_event_as($this->admin, 'Summit 2026');
    $second = create_event_as($this->admin, 'Summit 2027');

    $manager = Livewire::actingAs($this->admin)->test(EventManager::class)->call('editEvent', $second->id);
    upload_image($manager, 'banner')->call('save')->assertHasNoErrors();

    $customizer = Livewire::actingAs($this->admin)
        ->test(EventCustomizer::class)
        ->call('openCreateSpeakerModal')
        ->set('speaker_name', 'Ama Darko');
    upload_image($customizer, 'speaker_photo')->call('saveSpeaker')->assertHasNoErrors();

    expect($second->fresh()->banner_path)
        ->toContain("/orgs/{$this->org->id}/events/{$second->id}/banners/")
        ->and(EventSpeaker::query()->sole()->photo_path)
        ->toContain("/orgs/{$this->org->id}/events/{$first->id}/speakers/");
});

test('gallery images are filed under the active event and filtered by it', function (): void {
    $first = create_event_as($this->admin, 'Summit 2026');
    $second = create_event_as($this->admin, 'Summit 2027');

    $addImage = function (string $title): void {
        $studio = Livewire::actingAs($this->admin)
            ->test(BespokeStudio::class)
            ->set('new_image_source_mode', 'upload')
            ->set('new_image_title', $title)
            ->set('new_image_tag', 'Stage');

        upload_image($studio, 'new_image_file')->call('addGalleryImage')->call('saveGalleryPage')->assertHasNoErrors();
    };

    $addImage('First Keynote');
    ActiveEvent::set($second);
    $addImage('Second Keynote');

    $images = collect(EventGallery::all($this->org->fresh()))->keyBy('title');

    expect($images['First Keynote']['event_id'])
        ->toBe($first->id)
        ->and($images['First Keynote']['url'])
        ->toContain("/orgs/{$this->org->id}/events/{$first->id}/gallery/")
        ->and($images['Second Keynote']['event_id'])
        ->toBe($second->id)
        ->and($images['Second Keynote']['url'])
        ->toContain("/orgs/{$this->org->id}/events/{$second->id}/gallery/");

    // The admin grid follows the active event unless "All events" is chosen.
    Livewire::actingAs($this->admin)
        ->test(BespokeStudio::class)
        ->set('activeTab', 'gallery')
        ->assertSee('Second Keynote')
        ->assertDontSee('First Keynote')
        ->set('galleryFilter', 'all')
        ->assertSee('First Keynote');

    // Visitors see each event's own photos.
    expect(array_column(EventGallery::forEvent($this->org->fresh(), $first), 'title'))->toBe(['First Keynote']);
});

test('organisation-wide and legacy gallery images show for every event', function (): void {
    $event = Event::factory()->create(['organization_id' => $this->org->id]);
    $this->org->update([
        'settings' => [
            'bespoke' => [
                'gallery' => [
                    'images' => [
                        [
                            'id' => 'legacy',
                            'url' => 'https://cdn.example.com/legacy.jpg',
                            'title' => 'Legacy',
                            'tag' => 'Archive',
                        ],
                        [
                            'id' => 'other',
                            'event_id' => $event->id + 99,
                            'url' => 'https://cdn.example.com/other.jpg',
                            'title' => 'Other',
                            'tag' => 'Stage',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect(array_column(EventGallery::forEvent($this->org->fresh(), $event), 'id'))->toBe(['legacy']);
});

test('gallery items cannot be rewritten from the browser', function (): void {
    Livewire::actingAs($this->admin)->test(BespokeStudio::class)->set('gallery_images', [[
        'id' => 'x',
        'event_id' => null,
        'url' => 'https://evil.example/x.jpg',
        'title' => 'x',
        'caption' => '',
        'tag' => 'x',
    ]]);
})->throws(CannotUpdateLockedPropertyException::class);

/*
 |--------------------------------------------------------------------------
 | Issue C: uploaded images render for visitors
 |--------------------------------------------------------------------------
 */

test('uploaded banners and speaker photos render on the public event page from Cloudinary', function (): void {
    $event = Event::factory()->create(['organization_id' => $this->org->id]);
    ActiveEvent::set($event);

    $manager = Livewire::actingAs($this->admin)->test(EventManager::class)->call('editEvent', $event->id);
    upload_image($manager, 'banner')->call('save');
    $customizer = Livewire::actingAs($this->admin)
        ->test(EventCustomizer::class)
        ->call('openCreateSpeakerModal')
        ->set('speaker_name', 'Kofi Annan Jr');
    upload_image($customizer, 'speaker_photo')->call('saveSpeaker');

    $banner = App\Services\Media\CloudinaryImage::fromStored($event->fresh()->banner_path);
    $photo = App\Services\Media\CloudinaryImage::fromStored(EventSpeaker::query()->sole()->photo_path);
    $base = 'https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,';

    $this
        ->get(route('public.event.show', ['org_slug' => 'summit-org', 'event_slug' => $event->slug]))
        ->assertOk()
        ->assertSee('src="' . $base . "w_1600/v{$banner?->version}/{$banner?->publicId}\"", false)
        ->assertSee('src="' . $base . "w_200/v{$photo?->version}/{$photo?->publicId}\"", false);

    $this->get(route('public.tenant.home', ['org_slug' => 'summit-org']))->assertOk()->assertSee(
        $base . "w_1600/v{$banner?->version}/{$banner?->publicId}",
        false,
    );
});

test('uploaded gallery photos render with storage urls in the public gallery and lightbox', function (): void {
    Storage::fake('public');
    $event = Event::factory()->create(['organization_id' => $this->org->id]);
    // Missing local media resolves to null, so the uploaded photo must actually exist on the disk.
    Storage::disk('public')->put("events/{$event->id}/gallery/stage.jpg", 'jpeg-bytes');
    $this->org->update([
        'settings' => [
            'bespoke' => [
                'gallery' => [
                    'images' => [
                        [
                            'id' => 'up',
                            'event_id' => $event->id,
                            'url' => "events/{$event->id}/gallery/stage.jpg",
                            'title' => 'Stage Shot',
                            'tag' => 'Stage',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this
        ->get(route('public.tenant.page', ['org_slug' => 'summit-org', 'page' => 'gallery']))
        ->assertOk()
        ->assertSee('src="/storage/events/' . $event->id . '/gallery/stage.jpg"', false)
        ->assertDontSee('src="events/', false);
});
