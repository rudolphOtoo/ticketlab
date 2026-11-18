<?php

declare(strict_types=1);

use App\Livewire\Admin\DailySessionLedger;
use App\Livewire\Admin\EventManager;
use App\Livewire\Platform\EditOrganization;
use App\Models\Event;
use App\Models\Organization;
use App\Models\SessionLedger;
use App\Models\User;
use App\Support\ActiveEvent;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Production layout: public media on the "r2" disk, private files and Livewire temporary uploads on "r2-private".
 */
beforeEach(function (): void {
    config([
        'filesystems.media_disk' => 'r2',
        'filesystems.private_disk' => 'r2-private',
        'livewire.temporary_file_upload.disk' => 'r2-private',
        // Placeholder R2 settings, as production would have real ones (the disks themselves are faked below).
        'filesystems.disks.r2.key' => 'test-key',
        'filesystems.disks.r2.secret' => 'test-secret',
        'filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
        'filesystems.disks.r2.bucket' => 'media',
        'filesystems.disks.r2.url' => 'https://media.example.test',
        'filesystems.disks.r2-private.key' => 'test-key',
        'filesystems.disks.r2-private.secret' => 'test-secret',
        'filesystems.disks.r2-private.endpoint' => 'https://account.r2.cloudflarestorage.com',
        'filesystems.disks.r2-private.bucket' => 'private',
        // Livewire's own upload guard (also 5 MB) would refuse oversized files before our rules run; widen it here so
        // these tests exercise MediaUrl::UPLOAD_RULES themselves.
        'livewire.temporary_file_upload.rules' => ['nullable', 'file', 'max:12288'],
    ]);
    Storage::fake('r2');
    Storage::fake('r2-private');
    new ReflectionProperty(App\Support\StorageDisks::class, 'checked')->setValue(null, []);
    Storage::fake('local');
    Storage::fake('public');

    $this->superAdmin = User::factory()->create()->assignMembership('super_admin', null);
    $this->org = Organization::factory()->create(['slug' => 'gmsu']);
});

function upload_logo(object $test, UploadedFile $file): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs($test->superAdmin)
        ->test(EditOrganization::class, ['organization' => $test->org])
        ->set('contactEmail', 'info@gmsu.example.test')
        ->set('logo', $file)
        ->call('save');
}

test('only jpg, png and webp images up to 5 MB are accepted', function (UploadedFile $file, string $rule): void {
    upload_logo($this, $file)->assertHasErrors(['logo' => $rule]);

    expect($this->org->fresh()->logo_url)->toBeNull()->and(Storage::disk('r2')->allFiles())->toBe([]);
})->with([
    'svg (can carry scripts)' => [UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'), 'mimes'],
    'gif' => [UploadedFile::fake()->image('logo.gif'), 'mimes'],
    // A real 5.2 MB file: Livewire measures the stored temporary file, so a faked size would not count.
    'over 5 MB' => [UploadedFile::fake()->create('logo.png', 5200, 'image/png'), 'max'],
    'not an image' => [UploadedFile::fake()->create('logo.png', 10, 'application/pdf'), 'image'],
]);

test('an uploaded logo is stored per organisation on the media disk with WebP sizes, never upscaled', function (): void {
    upload_logo($this, UploadedFile::fake()->image('logo.png', 1000, 400))->assertHasNoErrors();

    $path = (string) $this->org->fresh()->logo_url;
    $width = static fn(string $file): int => (int) getimagesizefromstring((string) Storage::disk('r2')->get($file))[0];

    expect($path)->toStartWith("orgs/{$this->org->id}/logos/");
    Storage::disk('r2')->assertExists($path);
    expect($width(MediaUrl::variantPath($path, 'thumb')))
        ->toBe(200)
        ->and($width(MediaUrl::variantPath($path, 'medium')))
        ->toBe(800)
        // 1000 px original: the 1600 px "large" copy is not blown up.
        ->and($width(MediaUrl::variantPath($path, 'large')))
        ->toBe(1000)
        ->and($this->org->fresh()->logoUrl())
        ->toEndWith('@thumb.webp');
});

test('nothing is written to the server disk when the production disks are configured', function (): void {
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $event = Event::factory()->create(['organization_id' => $this->org->id]);
    ActiveEvent::set($event);

    upload_logo($this, UploadedFile::fake()->image('logo.png', 400, 400));
    Livewire::actingAs($admin)
        ->test(EventManager::class)
        ->call('editEvent', $event->id)
        ->set('banner', UploadedFile::fake()->image('banner.jpg', 1600, 700))
        ->call('save')
        ->assertHasNoErrors();

    expect(Storage::disk('local')->allFiles())
        ->toBe([])
        ->and(Storage::disk('public')->allFiles())
        ->toBe([])
        ->and($event->fresh()->banner_path)
        ->toStartWith("orgs/{$this->org->id}/events/{$event->id}/banners/");
    Storage::disk('r2')->assertExists((string) $event->fresh()->banner_path);
});

test('replacing, unlinking or removing a logo deletes the old upload and all its sizes', function (): void {
    upload_logo($this, UploadedFile::fake()->image('first.png', 900, 300));
    $first = (string) $this->org->fresh()->logo_url;

    upload_logo($this, UploadedFile::fake()->image('second.png', 900, 300));
    $second = (string) $this->org->fresh()->logo_url;

    Storage::disk('r2')->assertMissing([
        $first,
        MediaUrl::variantPath($first, 'thumb'),
        MediaUrl::variantPath($first, 'large'),
    ]);
    Storage::disk('r2')->assertExists($second);

    // Switching to a pasted link removes the upload; the link itself is stored and rendered as it is.
    Livewire::actingAs($this->superAdmin)
        ->test(EditOrganization::class, ['organization' => $this->org->fresh()])
        ->set('logoUrl', 'https://cdn.example.test/gmsu.png')
        ->call('save')
        ->assertHasNoErrors();

    Storage::disk('r2')->assertMissing([$second, MediaUrl::variantPath($second, 'medium')]);
    expect($this->org->fresh()->logoUrl())->toBe('https://cdn.example.test/gmsu.png');

    upload_logo($this, UploadedFile::fake()->image('third.png', 400, 400));
    $third = (string) $this->org->fresh()->logo_url;

    Livewire::actingAs($this->superAdmin)
        ->test(EditOrganization::class, ['organization' => $this->org->fresh()])
        ->call('removeLogo');

    Storage::disk('r2')->assertMissing([$third, MediaUrl::variantPath($third, 'thumb')]);
    expect($this->org->fresh()->logo_url)->toBeNull()->and(Storage::disk('r2')->allFiles())->toBe([]);
});

test('existing pasted URLs keep working and insecure links are never rendered', function (): void {
    $event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'banner_path' => 'https://cdn.example.test/banner.jpg',
    ]);

    expect($event->bannerUrl())->toBe('https://cdn.example.test/banner.jpg');

    $this->org->update(['logo_url' => 'javascript:alert(1)']);
    expect($this->org->fresh()->logoUrl())->toBeNull();
});

test('ledger PDFs go to the private disk and are handed out only as short-lived signed links after the scope check', function (): void {
    Storage::disk('r2-private')->buildTemporaryUrlsUsing(
        fn(
            string $path,
            DateTimeInterface $expiresAt,
        ): string => "https://private.example.test/{$path}?expires={$expiresAt->getTimestamp()}",
    );
    $admin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $ledger = SessionLedger::query()->create([
        'organization_id' => $this->org->id,
        'session_name' => 'Day 1',
        'opened_at' => now(),
    ]);

    $download = Livewire::actingAs($admin)
        ->test(DailySessionLedger::class)
        ->set('activeSessionId', $ledger->id)
        ->call('downloadPdfReport');

    $path = (string) $ledger->fresh()->snapshot_pdf_path;
    expect($path)->toStartWith("orgs/{$this->org->id}/ledgers/");
    Storage::disk('r2-private')->assertExists($path);
    expect(Storage::disk('r2')->allFiles())->toBe([])->and(Storage::disk('local')->allFiles())->toBe([]);

    $download->assertRedirect("https://private.example.test/{$path}?expires=" . now()->addMinutes(5)->getTimestamp());

    // Another organisation's admin cannot reach it: no link is generated at all.
    $outsider = User::factory()->create()->assignMembership('admin', Organization::factory()->create()->id);
    Livewire::actingAs($outsider)
        ->test(DailySessionLedger::class)
        ->set('activeSessionId', $ledger->id)
        ->call('downloadPdfReport')
        ->assertNoRedirect();
});

test('an R2 disk with missing settings fails with a message naming them, not a driver TypeError', function (): void {
    config(['filesystems.disks.r2.bucket' => null, 'filesystems.disks.r2.url' => '']);
    // Each test starts a fresh request in production, but the checker memoises per process: clear it here.
    new ReflectionProperty(App\Support\StorageDisks::class, 'checked')->setValue(null, []);

    expect(static fn() => MediaUrl::resolve('orgs/1/logos/a.png'))
        ->toThrow(RuntimeException::class, 'MEDIA_DISK=r2, but these settings are empty: R2_PUBLIC_BUCKET, MEDIA_URL');
});
