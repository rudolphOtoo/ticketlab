<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\GenerateImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Public media: organisation logos, event banners, speaker photos, gallery images and flyers.
 *
 * Media columns hold either a URL (pasted links and legacy values, kept working as they are) or a path on the media
 * disk (uploads). Uploads always go through Storage on filesystems.media_disk: "public" locally and "r2" (a public
 * Cloudflare R2 bucket behind MEDIA_URL) in production, so nothing is ever written to a serverless instance's disk.
 *
 * Every upload is namespaced per organisation (orgs/{org_id}/...), served with long cache headers, and gets WebP
 * copies in three sizes generated on the queue. Until those exist, the original is served.
 */
final class MediaUrl
{
    /** Validation for every image upload: real jpg/png/webp images up to 5 MB (no SVG, which can carry scripts). */
    public const array UPLOAD_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    /** WebP copies generated for every upload, by name and maximum width in pixels (never upscaled). */
    public const array SIZES = ['thumb' => 200, 'medium' => 800, 'large' => 1600];

    /** Files never change at a given path (names are random), so browsers and the CDN may cache them for a year. */
    public const string CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public static function disk(): string
    {
        return StorageDisks::assertUsable((string) config('filesystems.media_disk', 'public'), 'MEDIA_DISK');
    }

    public static function organizationDirectory(int $organizationId, string $kind): string
    {
        return "orgs/{$organizationId}/{$kind}";
    }

    /**
     * Where an event's uploads of one kind live, so every asset can be traced back to its organisation and event.
     */
    public static function eventDirectory(int $organizationId, int $eventId, string $kind): string
    {
        return "orgs/{$organizationId}/events/{$eventId}/{$kind}";
    }

    /**
     * Public URL for a stored path or pasted link. With a size, the WebP copy of that size once it exists.
     */
    public static function resolve(?string $path, ?string $size = null): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (self::isExternal($path)) {
            return $path;
        }

        // Neither a link we accept nor an upload path (e.g. "javascript:…"): never rendered.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1) {
            return null;
        }

        if ($size !== null && array_key_exists($size, self::SIZES) && self::hasVariants($path)) {
            $path = self::variantPath($path, $size);
        }

        // Local files are served by this app: verify the file physically exists on disk.
        if (config('filesystems.disks.' . self::disk() . '.driver') === 'local') {
            if (!Storage::disk(self::disk())->exists($path)) {
                return null;
            }

            $url = Storage::disk(self::disk())->url($path);
            $relative = parse_url($url, PHP_URL_PATH);

            return is_string($relative) ? $relative : $url;
        }

        return Storage::disk(self::disk())->url($path);
    }

    /**
     * Store an upload on the media disk with long cache headers and queue its WebP copies. Returns the stored path.
     */
    public static function store(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension());
        $path = Storage::disk(self::disk())->putFileAs(
            $directory,
            $file,
            Str::random(40) . '.' . ($extension !== '' ? $extension : 'jpg'),
            [
                'CacheControl' => self::CACHE_CONTROL,
                'ContentType' => $file->getMimeType() ?? 'application/octet-stream',
            ],
        );

        if ($path === false) {
            throw new RuntimeException("Could not store the uploaded file in [{$directory}].");
        }

        GenerateImageVariants::dispatch(self::disk(), $path);

        return $path;
    }

    /**
     * Remove an uploaded file and its WebP copies; pasted links are left alone.
     */
    public static function deleteStored(?string $path): void
    {
        if ($path === null || $path === '' || self::isExternal($path)) {
            return;
        }

        Storage::disk(self::disk())->delete([
            $path,
            ...array_map(static fn(string $size): string => self::variantPath($path, $size), array_keys(self::SIZES)),
        ]);
        Cache::forget(self::variantsKey($path));
    }

    /**
     * "orgs/1/logos/abc.png" → "orgs/1/logos/abc@medium.webp".
     */
    public static function variantPath(string $path, string $size): string
    {
        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $name = pathinfo($path, PATHINFO_FILENAME) . '@' . $size . '.webp';

        return $directory === '.' || $directory === '' ? $name : $directory . '/' . $name;
    }

    public static function markVariantsReady(string $path): void
    {
        Cache::forever(self::variantsKey($path), true);
    }

    public static function hasVariants(string $path): bool
    {
        return Cache::get(self::variantsKey($path)) === true;
    }

    public static function isExternal(?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }

        return Str::startsWith($path, ['http://', 'https://', '//', '/', 'data:']);
    }

    private static function variantsKey(string $path): string
    {
        return 'media:variants:' . sha1(self::disk() . '|' . $path);
    }
}
