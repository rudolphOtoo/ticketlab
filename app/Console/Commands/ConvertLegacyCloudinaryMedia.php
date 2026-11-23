<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Organization;
use App\Services\Media\CloudinaryClient;
use App\Services\Media\CloudinaryImage;
use App\Services\Media\LegacyCloudinaryPath;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Rewrites media values stored by the earlier Cloudinary setup (codebar-ag Flysystem disk: "logos/abc.jpg", put under
 * CLOUDINARY_FOLDER on Cloudinary) to ours, "cloudinary:v{version}/{public_id}".
 *
 * Each image is looked up with the Admin API, so the stored public_id and version are what Cloudinary really holds.
 * Dry run by default. Never deletes anything, on Cloudinary or in the database: an image that is not found is reported
 * and its value left as it is. Safe to run again: converted values are skipped.
 */
final class ConvertLegacyCloudinaryMedia extends Command
{
    protected $signature = 'media:convert-legacy-cloudinary
        {--execute : Write the converted values (without it, only report what would change)}
        {--folder= : The earlier disk\'s CLOUDINARY_FOLDER (defaults to the CLOUDINARY_FOLDER setting)}';

    protected $description = 'Convert image paths stored by the earlier Cloudinary disk to cloudinary:v{version}/{public_id}';

    /** Plain media columns: table => [model, column]. */
    private const array COLUMNS = [
        'organizations' => [Organization::class, 'logo_url'],
        'events' => [Event::class, 'banner_path'],
        'event_speakers' => [EventSpeaker::class, 'photo_path'],
    ];

    /** @var array<string, string|null> stored value => converted value (null: not found), one lookup per image */
    private array $lookups = [];

    /** @var list<array{string, string, string}> where, value, result */
    private array $report = [];

    private int $converted = 0;

    private int $missing = 0;

    private int $apiCalls = 0;

    /** Whether to write (--execute) or only report. */
    private bool $writes = false;

    /** The earlier disk's CLOUDINARY_FOLDER. */
    private ?string $folder = null;

    public function handle(CloudinaryClient $client): int
    {
        if (!$client->isConfigured()) {
            $this->error('CLOUDINARY_URL is not set, so images cannot be looked up. Nothing was changed.');

            return self::FAILURE;
        }

        // Artisan keeps one instance of each command, so a second run in the same process starts from zero here.
        $this->lookups = [];
        $this->report = [];
        $this->converted = 0;
        $this->missing = 0;
        $this->apiCalls = 0;

        $this->writes = (bool) $this->option('execute');
        $folder = $this->option('folder') ?? config('services.cloudinary.legacy_folder');
        $this->folder = is_string($folder) && trim($folder, ' /') !== '' ? trim($folder, ' /') : null;

        $this->line($this->writes ? 'Converting.' : 'Dry run: nothing is written. Add --execute to convert.');
        $this->line('Earlier folder: ' . ($this->folder ?? '(none)'));

        try {
            foreach (self::COLUMNS as $table => [$model, $column]) {
                $this->convertColumn($client, $model, $table, $column);
            }

            $this->convertGalleries($client);
        } catch (Throwable $exception) {
            $this->renderReport();
            $this->error('Stopped: ' . $exception->getMessage());
            $this->line(
                'Values converted before this point are saved (with --execute). Run the command again to continue.',
            );

            return self::FAILURE;
        }

        $this->renderReport();
        $this->line(sprintf(
            '%s %d, not converted %d, Admin API lookups %d. Nothing was deleted.',
            $this->writes ? 'Converted' : 'Would convert',
            $this->converted,
            $this->missing,
            $this->apiCalls,
        ));

        if ($this->missing > 0) {
            $this->warn(
                'Images not converted keep their old value. Check them in the Cloudinary console, or re-upload them in '
                . 'the studio. Until then they show only while MEDIA_DISK=cloudinary and CLOUDINARY_FOLDER are set.',
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param class-string<Model> $model
     */
    private function convertColumn(CloudinaryClient $client, string $model, string $table, string $column): void
    {
        $model::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->where($column, 'not like', CloudinaryImage::PREFIX . '%')
            ->select(['id', $column])
            ->chunkById(100, function ($rows) use ($client, $model, $column, $table): void {
                foreach ($rows as $row) {
                    $value = (string) $row->getAttribute($column);
                    $converted = $this->convertValue($client, $value, "{$table}#{$row->getKey()} {$column}");

                    if ($converted === null || !$this->writes) {
                        continue;
                    }

                    // Only if the value is still the one read: an admin may have replaced the image meanwhile.
                    $model::query()
                        ->whereKey($row->getKey())
                        ->where($column, $value)
                        ->toBase()
                        ->update([
                            $column => $converted,
                        ]);
                }
            });
    }

    private function convertGalleries(CloudinaryClient $client): void
    {
        Organization::query()
            ->whereNotNull('settings')
            ->select(['id', 'settings'])
            ->chunkById(100, function ($organizations) use ($client): void {
                foreach ($organizations as $organization) {
                    $changes = [];

                    foreach (self::galleryUrls($organization) as $index => $url) {
                        $where = "organizations#{$organization->id} gallery image " . ($index + 1);
                        $converted = $this->convertValue($client, $url, $where);

                        if ($converted !== null) {
                            $changes[$url] = $converted;
                        }
                    }

                    if ($changes !== [] && $this->writes) {
                        self::saveGallery((int) $organization->id, $changes);
                    }
                }
            });
    }

    /**
     * @return array<int, string> position in the gallery => stored url
     */
    private static function galleryUrls(Organization $organization): array
    {
        $images = $organization->settings['bespoke']['gallery']['images'] ?? null;
        $urls = [];

        foreach (is_array($images) ? $images : [] as $index => $image) {
            if (!is_int($index) || !is_array($image) || !is_string($image['url'] ?? null)) {
                continue;
            }

            $urls[$index] = $image['url'];
        }

        return $urls;
    }

    /**
     * Re-reads the settings under a row lock and replaces only the urls that still hold the value read.
     *
     * @param array<string, string> $changes old url => converted value
     */
    private static function saveGallery(int $organizationId, array $changes): void
    {
        DB::transaction(static function () use ($organizationId, $changes): void {
            $organization = Organization::query()->lockForUpdate()->find($organizationId);
            $settings = $organization?->settings;
            $bespoke = $settings['bespoke'] ?? null;
            $gallery = is_array($bespoke) ? $bespoke['gallery'] ?? null : null;
            $images = is_array($gallery) ? $gallery['images'] ?? null : null;

            if ($organization === null || !is_array($settings) || !is_array($bespoke) || !is_array($gallery)) {
                return;
            }

            $updated = [];

            foreach (is_array($images) ? $images : [] as $index => $image) {
                $url = is_array($image) ? $image['url'] ?? null : null;
                $updated[$index] = is_array($image) && is_string($url) && array_key_exists($url, $changes)
                    ? [...$image, 'url' => $changes[$url]]
                    : $image;
            }

            $gallery['images'] = $updated;
            $bespoke['gallery'] = $gallery;
            $settings['bespoke'] = $bespoke;
            $organization->settings = $settings;
            $organization->saveQuietly();
        });
    }

    /**
     * The converted value for an earlier-disk path, or null when it is not one or the image was not found.
     */
    private function convertValue(CloudinaryClient $client, string $value, string $where): ?string
    {
        $legacy = LegacyCloudinaryPath::parse($value, $this->folder);

        if ($legacy === null) {
            return null; // A link, an already converted value, or not a path: left as it is.
        }

        if (array_filter($legacy->candidates(), CloudinaryImage::isValidPublicId(...)) === []) {
            $this->missing++;
            $this->report[] = [$where, $value, 'NOT CONVERTED: spaces or brackets in the name; re-upload it'];

            return null;
        }

        if (!array_key_exists($value, $this->lookups)) {
            $this->lookups[$value] = $this->lookUp($client, $legacy);
        }

        $converted = $this->lookups[$value];

        if ($converted === null) {
            $this->missing++;
            $this->report[] = [$where, $value, 'NOT FOUND on Cloudinary (left as it is)'];

            return null;
        }

        $this->converted++;
        $this->report[] = [$where, $value, $converted];

        return $converted;
    }

    private function lookUp(CloudinaryClient $client, LegacyCloudinaryPath $legacy): ?string
    {
        foreach ($legacy->candidates() as $publicId) {
            if (!CloudinaryImage::isValidPublicId($publicId)) {
                continue;
            }

            $this->apiCalls++;
            $image = $client->image($publicId);

            if ($image === null) {
                continue;
            }

            if ($image['version'] < 1) {
                throw new RuntimeException("Cloudinary returned no version for {$publicId}.");
            }

            return new CloudinaryImage($publicId, $image['version'])->toStored();
        }

        return null;
    }

    private function renderReport(): void
    {
        if ($this->report === []) {
            $this->line('No images from the earlier Cloudinary disk found.');

            return;
        }

        $this->table(['Where', 'Stored value', 'Result'], $this->report);
    }
}
