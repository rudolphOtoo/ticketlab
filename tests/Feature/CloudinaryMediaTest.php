<?php

declare(strict_types=1);

use App\Livewire\Admin\BespokeStudio;
use App\Livewire\Admin\DailySessionLedger;
use App\Livewire\Admin\EventCustomizer;
use App\Livewire\Admin\EventManager;
use App\Livewire\Platform\EditOrganization;
use App\Livewire\Platform\OnboardOrganization;
use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Organization;
use App\Models\SessionLedger;
use App\Models\Subdivision;
use App\Models\User;
use App\Services\Media\CloudinaryImage;
use App\Services\Media\ImagePreset;
use App\Support\ActiveEvent;
use App\Support\EventGallery;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 * Images go from the browser straight to Cloudinary (signed by us), and only a checked public_id and version are
 * stored. Cloudinary itself is the fake bound in tests/Pest.php.
 */

beforeEach(function (): void {
    $this->superAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $this->org = Organization::factory()->create(['slug' => 'gmsu']);
});

function edit_org(object $test): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs($test->superAdmin)->test(EditOrganization::class, ['organization' => $test->org])->set(
        'contactEmail',
        'info@gmsu.example.test',
    );
}

/*
 |--------------------------------------------------------------------------
 | Signing and checking uploads
 |--------------------------------------------------------------------------
 */

test('uploads are signed for the organisation folder, images only, without ever sending the secret', function (): void {
    $signed = edit_org($this)->instance()->signImageUpload('logo');

    expect($signed['url'])
        ->toBe('https://api.cloudinary.com/v1_1/demo-cloud/image/upload')
        ->and($signed['fields']['folder'])
        ->toBe("ticketlab/testing/orgs/{$this->org->id}/logos")
        ->and($signed['fields']['allowed_formats'])
        ->toBe('jpg,png,webp')
        ->and($signed['fields']['api_key'])
        ->toBe('111222333')
        ->and($signed['fields']['signature'])
        ->toMatch('/^[0-9a-f]{40}$/')
        ->and($signed['maxBytes'])
        ->toBe(5 * 1024 * 1024)
        ->and(json_encode($signed))
        ->not->toContain('fake-cloudinary-secret');

    // Exactly the posted fields are signed, so Cloudinary refuses a changed folder or format list.
    expect(cloudinary()->signed)->toBe([[
        'allowed_formats' => 'jpg,png,webp',
        'folder' => "ticketlab/testing/orgs/{$this->org->id}/logos",
        'timestamp' => $signed['fields']['timestamp'],
    ]]);
});

test('without CLOUDINARY_URL uploads are refused with a clear message and pasted links still work', function (): void {
    cloudinary()->configured = false;

    edit_org($this)
        ->call('signImageUpload', 'logo')
        ->assertHasErrors([
            'logo' => 'Image uploads are not set up yet (CLOUDINARY_URL). Paste an https image link instead.',
        ])
        ->set('logoUrl', 'https://cdn.example.test/logo.png')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->org->fresh()->logoUrl())->toBe('https://cdn.example.test/logo.png');
});

test('an upload is refused unless Cloudinary signed it, it is in this organisation, and it is a small image', function (
    Closure $tamper,
    string $message,
    bool $deleted,
): void {
    $component = edit_org($this);
    $folder = $component->instance()->signImageUpload('logo')['fields']['folder'];
    [$publicId, $version, $signature] = $tamper($folder);

    $component
        ->call('acceptImageUpload', 'logo', $publicId, $version, $signature)
        ->assertHasErrors(['logo' => $message])
        ->assertSet('pendingImages', []);

    expect(in_array($publicId, cloudinary()->destroyed, true))->toBe($deleted);
})->with([
    'forged signature' => [
        static fn(string $folder): array => [$folder . '/x', 1, str_repeat('0', 40)],
        'That upload could not be verified. Please try again.',
        false,
    ],
    'another organisation\'s image' => [
        static function (): array {
            $other = cloudinary()->upload('ticketlab/testing/orgs/999/logos');

            return [$other['public_id'], $other['version'], $other['signature']];
        },
        'That upload does not belong here. Please upload again.',
        false,
    ],
    'over 5 MB' => [
        static function (string $folder): array {
            $big = cloudinary()->upload($folder, bytes: 6 * 1024 * 1024);

            return [$big['public_id'], $big['version'], $big['signature']];
        },
        'Images must be JPG, PNG or WebP and at most 5 MB.',
        true,
    ],
    'not jpg, png or webp' => [
        static function (string $folder): array {
            $gif = cloudinary()->upload($folder, format: 'gif');

            return [$gif['public_id'], $gif['version'], $gif['signature']];
        },
        'Images must be JPG, PNG or WebP and at most 5 MB.',
        true,
    ],
]);

test('the browser cannot set a pending upload itself; only acceptImageUpload() can', function (): void {
    expect(fn() => edit_org($this)->set('pendingImages', [
        'logo' => 'cloudinary:v1/ticketlab/testing/orgs/999/logos/x',
    ]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('a component refuses upload fields it does not have', function (): void {
    edit_org($this)->call('signImageUpload', 'banner')->assertHasErrors([
        'banner' => 'This form does not take that upload.',
    ]);
});

/*
 |--------------------------------------------------------------------------
 | Stored value and delivery URLs
 |--------------------------------------------------------------------------
 */

test('a saved upload stores the public_id and version, and pages build f_auto,q_auto URLs that never upscale', function (): void {
    upload_image(edit_org($this), 'logo')->call('save')->assertHasNoErrors();

    $stored = (string) $this->org->fresh()->logo_url;
    $image = CloudinaryImage::fromStored($stored);

    expect($stored)
        ->toStartWith('cloudinary:v')
        ->not
        ->toContain('https://')
        ->and($image?->publicId)
        ->toStartWith("ticketlab/testing/orgs/{$this->org->id}/logos/")
        ->and($this->org->fresh()->logoUrl())
        ->toBe(
            "https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_200/v{$image?->version}/{$image?->publicId}",
        )
        ->and(MediaUrl::srcset($stored, ImagePreset::Hero))
        ->toContain('c_limit,w_640/')
        ->toContain(' 640w, ')
        ->toContain('c_limit,w_1600/')
        ->toEndWith(' 1600w');
});

test('the event page serves the hero and speakers with a srcset', function (): void {
    $event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'slug' => 'convention',
        'status' => 'published',
        'banner_path' => 'cloudinary:v5/ticketlab/testing/orgs/1/events/1/banners/hero',
    ]);
    EventSpeaker::create([
        'organization_id' => $this->org->id,
        'event_id' => $event->id,
        'name' => 'Rev. Mensah',
        'photo_path' => 'cloudinary:v6/ticketlab/testing/orgs/1/events/1/speakers/mensah',
        'sort_order' => 1,
    ]);

    $this
        ->get('/gmsu/convention')
        ->assertOk()
        ->assertSee(
            'srcset="https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_640/v5/ticketlab/testing/orgs/1/events/1/banners/hero 640w',
            escape: false,
        )
        ->assertSee('fetchpriority="high"', escape: false)
        ->assertSee(
            'f_auto,q_auto,c_limit,w_100/v6/ticketlab/testing/orgs/1/events/1/speakers/mensah 100w',
            escape: false,
        );
});

test('existing values keep working: https links as they are, older paths from LEGACY_MEDIA_URL', function (): void {
    $event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'banner_path' => 'https://cdn.example.test/banner.jpg',
    ]);
    expect($event->bannerUrl())->toBe('https://cdn.example.test/banner.jpg');

    config(['filesystems.legacy_media_url' => 'https://media.example.test/']);
    expect(MediaUrl::resolve('orgs/1/logos/abc.png'))->toBe('https://media.example.test/orgs/1/logos/abc.png');

    config(['filesystems.legacy_media_url' => null]);
    Storage::fake('public');
    Storage::disk('public')->put('orgs/1/logos/abc.png', 'png');
    expect(MediaUrl::resolve('orgs/1/logos/abc.png'))->toBe('/storage/orgs/1/logos/abc.png');

    $this->org->update(['logo_url' => 'javascript:alert(1)']);
    expect($this->org->fresh()->logoUrl())->toBeNull();
});

test('pasted links must be https', function (): void {
    edit_org($this)
        ->set('logoUrl', 'http://insecure.example.test/logo.png')
        ->call('save')
        ->assertHasErrors(['logoUrl']);
});

/*
 |--------------------------------------------------------------------------
 | Deleting replaced and abandoned images
 |--------------------------------------------------------------------------
 */

test('replacing or removing a logo deletes the old image on Cloudinary; pasted links are never touched', function (): void {
    upload_image(edit_org($this), 'logo')->call('save');
    $first = CloudinaryImage::fromStored($this->org->fresh()->logo_url)?->publicId;

    upload_image(edit_org($this), 'logo')->call('save');
    $second = CloudinaryImage::fromStored($this->org->fresh()->logo_url)?->publicId;
    expect(cloudinary()->destroyed)->toBe([$first]);

    edit_org($this)->call('removeLogo');
    expect(cloudinary()->destroyed)->toBe([$first, $second])->and($this->org->fresh()->logo_url)->toBeNull();

    edit_org($this)->set('logoUrl', 'https://cdn.example.test/logo.png')->call('save');
    edit_org($this)->call('removeLogo');
    expect(cloudinary()->destroyed)->toBe([$first, $second]);
});

test('a second upload before saving, or a cancelled form, deletes the upload nobody will use', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $event = Event::factory()->create(['organization_id' => $this->org->id]);

    $component = Livewire::actingAs($admin)->test(EventManager::class)->call('editEvent', $event->id);
    upload_image($component, 'banner');
    $abandoned = array_key_first(cloudinary()->images);

    upload_image($component, 'banner');
    expect(cloudinary()->destroyed)->toBe([$abandoned]);

    $kept = (string) $component->instance()->pendingImage('banner');
    $component->call('closeModal');

    expect(cloudinary()->destroyed)
        ->toBe([$abandoned, CloudinaryImage::fromStored($kept)?->publicId])
        ->and($event->fresh()->banner_path)
        ->toBeNull();
});

test('a new event\'s banner is uploaded into the organisation folder and saved on the event', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);

    $component = Livewire::actingAs($admin)->test(EventManager::class)->call('openCreateModal');
    expect($component->instance()->signImageUpload('banner')['fields']['folder'])
        ->toBe("ticketlab/testing/orgs/{$this->org->id}/banners");

    upload_image($component, 'banner')->set('title', 'Easter Convention')->call('save')->assertHasNoErrors();

    $event = Event::query()->where('title', 'Easter Convention')->sole();
    expect($event->banner_path)
        ->toStartWith('cloudinary:v')
        ->and(cloudinary()->destroyed)
        ->toBe([])
        ->and($component->get('pendingImages'))
        ->toBe([]);
});

test('an onboarding logo moves from the onboarding folder into the new organisation\'s folder', function (): void {
    $component = Livewire::actingAs($this->superAdmin)->test(OnboardOrganization::class);
    $folder = $component->instance()->signImageUpload('logo')['fields']['folder'];
    expect($folder)->toStartWith('ticketlab/testing/onboarding/');

    upload_image($component, 'logo')
        ->set('name', 'Grace Chapel')
        ->set('slug', 'grace-chapel')
        ->set('contactEmail', 'office@grace.example.test')
        ->set('zones', [['name' => 'Accra', 'subaccount' => '']])
        ->set('adminName', 'Ama Admin')
        ->set('adminEmail', 'ama@grace.example.test')
        ->call('save')
        ->assertHasNoErrors();

    $organization = Organization::query()->where('slug', 'grace-chapel')->sole();
    $logo = CloudinaryImage::fromStored($organization->logo_url);

    expect($logo?->publicId)
        ->toStartWith("ticketlab/testing/orgs/{$organization->id}/logos/")
        ->and(cloudinary()->renamed)
        ->toHaveCount(1)
        ->and(cloudinary()->renamed[0]['from'])
        ->toStartWith($folder . '/');
});

test('a speaker photo replaced on save deletes the stored one, and the photo field cannot name another image', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $event = Event::factory()->create(['organization_id' => $this->org->id]);
    ActiveEvent::set($event);
    $speaker = EventSpeaker::create([
        'organization_id' => $this->org->id,
        'event_id' => $event->id,
        'name' => 'Rev. Mensah',
        'photo_path' =>
            'cloudinary:v1/ticketlab/testing/orgs/' . $this->org->id . '/events/' . $event->id . '/speakers/old',
        'sort_order' => 1,
    ]);

    $component = Livewire::actingAs($admin)->test(EventCustomizer::class)->call('editSpeaker', $speaker->id);
    upload_image($component, 'speaker_photo')->call('saveSpeaker')->assertHasNoErrors();

    expect(cloudinary()->destroyed)
        ->toBe(["ticketlab/testing/orgs/{$this->org->id}/events/{$event->id}/speakers/old"])
        ->and($speaker->fresh()->photo_path)
        ->toStartWith('cloudinary:v')
        ->toContain("/orgs/{$this->org->id}/events/{$event->id}/speakers/");

    // The field is editable in the browser: typing another organisation's stored image (or a path) into it is
    // refused, so it can neither be shown here nor deleted on the next replacement.
    Livewire::actingAs($admin)
        ->test(EventCustomizer::class)
        ->call('editSpeaker', $speaker->id)
        ->set('speaker_photo_path', 'cloudinary:v1/ticketlab/testing/orgs/999/logos/theirs')
        ->call('saveSpeaker')
        ->assertHasErrors(['speaker_photo_path']);

    expect(cloudinary()->destroyed)->toHaveCount(1);
});

test('a published gallery image is deleted only when the gallery is saved; an unsaved upload at once', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $event = Event::factory()->create(['organization_id' => $this->org->id]);
    ActiveEvent::set($event);

    $component = Livewire::actingAs($admin)->test(BespokeStudio::class)->set('new_image_source_mode', 'upload');
    upload_image($component, 'new_image_file')->call('addGalleryImage')->assertHasNoErrors()->call('saveGalleryPage');
    $published = EventGallery::all($this->org->fresh())[0];

    $component->call('removeGalleryImage', $published['id']);
    expect(cloudinary()->destroyed)->toBe([]);
    expect(EventGallery::all($this->org->fresh()))->toHaveCount(1);

    $component->call('saveGalleryPage');
    expect(cloudinary()->destroyed)->toBe([CloudinaryImage::fromStored($published['url'])?->publicId]);

    upload_image($component, 'new_image_file')->call('addGalleryImage');
    $unsaved = collect($component->get('gallery_images'))->last();
    $component->call('removeGalleryImage', $unsaved['id']);

    expect(cloudinary()->destroyed)->toContain(CloudinaryImage::fromStored($unsaved['url'])?->publicId);
});

/*
 |--------------------------------------------------------------------------
 | Ledger PDFs: streamed, never stored
 |--------------------------------------------------------------------------
 */

test('locking a ledger freezes its figures and the PDF is streamed after the scope check, never stored', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $zone = Subdivision::factory()->create(['organization_id' => $this->org->id, 'name' => 'Kumasi']);
    $ledger = SessionLedger::query()->create([
        'organization_id' => $this->org->id,
        'session_name' => 'Day 1',
        'opened_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(DailySessionLedger::class)
        ->set('activeSessionId', $ledger->id)
        ->call('lockSession')
        ->call('downloadPdfReport')
        ->assertFileDownloaded('Session_Ledger_Day_1.pdf', contentType: 'application/pdf');

    expect($ledger->fresh()->snapshot['zone_breakdown'])
        ->toBe([['name' => 'Kumasi', 'code' => $zone->slug, 'admitted' => 0]])
        ->and(Storage::disk('local')->allFiles())
        ->toBe([])
        ->and(Storage::disk('public')->allFiles())
        ->toBe([]);

    // Another organisation's admin gets nothing.
    $outsider = User::factory()->create()->assignMembership('admin', Organization::factory()->create()->id);
    Livewire::actingAs($outsider)
        ->test(DailySessionLedger::class)
        ->set('activeSessionId', $ledger->id)
        ->call('downloadPdfReport')
        ->assertNoFileDownloaded();
});
