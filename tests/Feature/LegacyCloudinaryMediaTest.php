<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Organization;
use App\Services\Media\CloudinaryImage;
use App\Services\Media\ImagePreset;
use App\Services\Media\LegacyCloudinaryPath;
use App\Support\EventGallery;
use App\Support\MediaUrl;

/*
 * Images stored by the earlier Cloudinary setup (codebar-ag Flysystem disk, MEDIA_DISK=cloudinary,
 * CLOUDINARY_FOLDER=ticketlab): the Laravel path ("logos/abc.jpg") in the column, the image on Cloudinary under the
 * folder. They keep showing after our release deploys, and media:convert-legacy-cloudinary rewrites them to
 * "cloudinary:v{version}/{public_id}". Cloudinary is the fake bound in tests/Pest.php.
 */

function earlier_cloudinary_disk(?string $folder = 'ticketlab'): void
{
    config([
        'services.cloudinary.legacy_disk' => 'cloudinary',
        'services.cloudinary.legacy_folder' => $folder,
        'filesystems.legacy_media_url' => 'https://old-bucket.example.test',
    ]);
}

function gallery_with(Organization $org, string ...$urls): void
{
    $settings = (array) $org->settings;
    $settings['bespoke']['gallery']['images'] = array_map(static fn(string $url): array => [
        'id' => md5($url),
        'event_id' => null,
        'url' => $url,
        'title' => 'Moment',
    ], $urls);
    $org->update(['settings' => $settings]);
}

describe('serving the earlier format before conversion', function (): void {
    it('builds the URL the earlier disk built, with our format, quality and width', function (): void {
        earlier_cloudinary_disk();
        $org = Organization::factory()->create(['logo_url' => 'logos/AbC123.jpg']);

        expect($org->logoUrl(ImagePreset::Logo))
            ->toBe(
                'https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_200/v1/ticketlab/logos/AbC123.jpg',
            )
            ->and(MediaUrl::srcset('logos/AbC123.jpg', ImagePreset::Logo))
            ->toBe(
                'https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_100/v1/ticketlab/logos/AbC123.jpg 100w, '
                . 'https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_200/v1/ticketlab/logos/AbC123.jpg 200w',
            );
    });

    it('serves banners, speaker photos and gallery images the same way', function (): void {
        earlier_cloudinary_disk();
        $event = Event::factory()->create(['banner_path' => 'banners/b1.png']);
        $speaker = EventSpeaker::factory()->create(['event_id' => $event->id, 'photo_path' => 'speakers/s1.webp']);
        gallery_with($event->organization, 'gallery/g1.jpg');

        $base = 'https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_';

        expect($event->bannerUrl(ImagePreset::Hero))
            ->toBe($base . '1600/v1/ticketlab/banners/b1.png')
            ->and($speaker->photoUrl(ImagePreset::Card))
            ->toBe($base . '800/v1/ticketlab/speakers/s1.webp')
            ->and(EventGallery::forEvent($event->organization->fresh(), $event)[0]['src'])
            ->toBe($base . '1600/v1/ticketlab/gallery/g1.jpg');
    });

    it('encodes each path segment as the SDK did, and works without a folder', function (): void {
        earlier_cloudinary_disk(folder: null);

        expect(MediaUrl::resolve('logos/my logo (1).PNG', ImagePreset::Logo))
            ->toBe(
                'https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_200/v1/logos/my%20logo%20%281%29.PNG',
            );
    });

    it('never renders a path that climbs out of its folder or carries a scheme', function (string $value): void {
        earlier_cloudinary_disk();

        expect(MediaUrl::resolve($value, ImagePreset::Logo))->toBeNull();
    })->with(['../secrets.jpg', 'logos/../../x.jpg', 'javascript:alert(1)', 'logos/<script>.jpg']);

    it('leaves converted values, links and Cloudinary-less setups alone', function (): void {
        earlier_cloudinary_disk();

        expect(MediaUrl::resolve('cloudinary:v5/ticketlab/logos/abc.jpg', ImagePreset::Logo))
            ->toBe(
                'https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_200/v5/ticketlab/logos/abc.jpg',
            )
            ->and(MediaUrl::resolve('https://cdn.example.test/a.jpg'))
            ->toBe('https://cdn.example.test/a.jpg');

        cloudinary()->configured = false;

        expect(MediaUrl::resolve('logos/abc.jpg'))->toBeNull();
    });

    it('serves plain paths from the old bucket once MEDIA_DISK is no longer cloudinary', function (): void {
        earlier_cloudinary_disk();
        config(['services.cloudinary.legacy_disk' => null]);

        expect(MediaUrl::resolve('orgs/1/logos/abc.png'))->toBe('https://old-bucket.example.test/orgs/1/logos/abc.png');
    });

    it('does not delete an unconverted image when it is replaced', function (): void {
        earlier_cloudinary_disk();
        cloudinary()->seed('ticketlab/logos/abc.jpg');

        MediaUrl::deleteStored('logos/abc.jpg');

        expect(cloudinary()->destroyed)->toBe([])->and(cloudinary()->images)->toHaveKey('ticketlab/logos/abc.jpg');
    });

    it('lists the public_ids to look up, most likely first', function (): void {
        expect(LegacyCloudinaryPath::parse('logos/abc.jpg', '/ticketlab/')?->candidates())->toBe([
            'ticketlab/logos/abc.jpg',
            'ticketlab/logos/abc',
            'logos/abc.jpg',
            'logos/abc',
        ]);
    });
});

describe('media:convert-legacy-cloudinary', function (): void {
    beforeEach(function (): void {
        earlier_cloudinary_disk();
        $this->org = Organization::factory()->create(['logo_url' => 'logos/logo1.jpg']);
        $this->event = Event::factory()->create([
            'organization_id' => $this->org->id,
            'banner_path' => 'banners/banner1.png',
        ]);
        $this->speaker = EventSpeaker::factory()->create([
            'event_id' => $this->event->id,
            'photo_path' => 'speakers/speaker1.jpg',
        ]);
        gallery_with($this->org, 'gallery/g1.jpg', 'https://cdn.example.test/pasted.jpg', 'gallery/g2.jpg');

        // Fixed-folder accounts keep the folder and the extension in the public_id; one image renamed by hand lost
        // its extension; one from a dynamic-folder account has no folder in its public_id.
        cloudinary()->seed('ticketlab/logos/logo1.jpg', version: 1_650_000_001);
        cloudinary()->seed('ticketlab/banners/banner1', version: 1_650_000_002, format: 'png');
        cloudinary()->seed('speakers/speaker1.jpg', version: 1_650_000_003);
        cloudinary()->seed('ticketlab/gallery/g1.jpg', version: 1_650_000_004);
        cloudinary()->seed('ticketlab/gallery/g2.jpg', version: 1_650_000_005);
    });

    it('only reports on a dry run', function (): void {
        $this
            ->artisan('media:convert-legacy-cloudinary')
            ->expectsOutputToContain('Dry run: nothing is written')
            ->expectsOutputToContain('Would convert 5, not converted 0')
            ->assertSuccessful();

        expect($this->org->fresh()->logo_url)
            ->toBe('logos/logo1.jpg')
            ->and($this->event->fresh()->banner_path)
            ->toBe('banners/banner1.png');
    });

    it('converts every media column and gallery image to the public_id and version Cloudinary holds', function (): void {
        $this
            ->artisan('media:convert-legacy-cloudinary', ['--execute' => true])
            ->expectsOutputToContain('Converted 5, not converted 0')
            ->assertSuccessful();

        $gallery = EventGallery::all($this->org->fresh());

        expect($this->org->fresh()->logo_url)
            ->toBe('cloudinary:v1650000001/ticketlab/logos/logo1.jpg')
            ->and($this->event->fresh()->banner_path)
            ->toBe('cloudinary:v1650000002/ticketlab/banners/banner1')
            ->and($this->speaker->fresh()->photo_path)
            ->toBe('cloudinary:v1650000003/speakers/speaker1.jpg')
            ->and(array_column($gallery, 'url'))
            ->toBe([
                'cloudinary:v1650000004/ticketlab/gallery/g1.jpg',
                'https://cdn.example.test/pasted.jpg',
                'cloudinary:v1650000005/ticketlab/gallery/g2.jpg',
            ])
            ->and(array_column($gallery, 'title'))
            ->toBe(['Moment', 'Moment', 'Moment'])
            ->and(CloudinaryImage::isStored($this->org->fresh()->logo_url))
            ->toBeTrue()
            // The converted logo is served at the same public_id, now with its real version.
            ->and($this->org->fresh()->logoUrl(ImagePreset::Logo))
            ->toBe(
                'https://res.cloudinary.com/demo-cloud/image/upload/f_auto,q_auto,c_limit,w_200/v1650000001/ticketlab/logos/logo1.jpg',
            );
    });

    it('reports images it cannot find, leaves them as they are and deletes nothing', function (): void {
        unset(cloudinary()->images['ticketlab/gallery/g2.jpg']);
        $this->speaker->update(['photo_path' => 'speakers/my photo.jpg']);

        $this
            ->artisan('media:convert-legacy-cloudinary', ['--execute' => true])
            ->expectsOutputToContain('NOT FOUND on Cloudinary')
            ->expectsOutputToContain('NOT CONVERTED: spaces or brackets')
            ->expectsOutputToContain('Converted 3, not converted 2')
            ->assertFailed();

        expect(array_column(EventGallery::all($this->org->fresh()), 'url')[2])
            ->toBe('gallery/g2.jpg')
            ->and($this->speaker->fresh()->photo_path)
            ->toBe('speakers/my photo.jpg')
            ->and(cloudinary()->destroyed)
            ->toBe([])
            ->and(cloudinary()->renamed)
            ->toBe([]);
    });

    it('can run again: converted values are skipped', function (): void {
        $this->artisan('media:convert-legacy-cloudinary', ['--execute' => true])->assertSuccessful();

        $this
            ->artisan('media:convert-legacy-cloudinary', ['--execute' => true])
            ->expectsOutputToContain('No images from the earlier Cloudinary disk found.')
            ->expectsOutputToContain('Converted 0, not converted 0, Admin API lookups 0')
            ->assertSuccessful();
    });

    it('takes the folder from --folder when CLOUDINARY_FOLDER is already gone', function (): void {
        config(['services.cloudinary.legacy_folder' => null]);

        $this
            ->artisan('media:convert-legacy-cloudinary', ['--execute' => true, '--folder' => 'ticketlab'])
            ->expectsOutputToContain('Converted 5')
            ->assertSuccessful();

        expect($this->org->fresh()->logo_url)->toBe('cloudinary:v1650000001/ticketlab/logos/logo1.jpg');
    });

    it('refuses to run without Cloudinary credentials', function (): void {
        cloudinary()->configured = false;

        $this
            ->artisan('media:convert-legacy-cloudinary', ['--execute' => true])
            ->expectsOutputToContain('CLOUDINARY_URL is not set')
            ->assertFailed();

        expect($this->org->fresh()->logo_url)->toBe('logos/logo1.jpg');
    });
});
