<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Media\CloudinaryClient;
use App\Services\Media\CloudinaryImage;
use App\Services\Media\ImagePreset;
use App\Services\Media\LegacyCloudinaryPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Public images: organisation logos, event banners, speaker photos, gallery images and flyer backgrounds.
 *
 * A media column holds one of three things, and all keep working:
 * - a Cloudinary image, "cloudinary:v{version}/{public_id}" (every upload since the move to Cloudinary);
 * - a pasted link ("https://…"), shown as it is;
 * - a path from an earlier setup: while MEDIA_DISK=cloudinary is still set, a path the earlier Cloudinary disk stored
 *   ("logos/abc.jpg", served from Cloudinary under CLOUDINARY_FOLDER, see LegacyCloudinaryPath) until
 *   media:convert-legacy-cloudinary has converted it; otherwise a path from before Cloudinary
 *   ("orgs/1/logos/abc.png"), served from LEGACY_MEDIA_URL (the old public bucket's address) when set, otherwise
 *   from the local public disk.
 *
 * Uploads go from the browser straight to Cloudinary (see CloudinaryUploads), into a folder per organisation:
 * ticketlab/{APP_ENV}/orgs/{org_id}/...
 */
final class MediaUrl
{
    /** Formats accepted for uploads; Cloudinary enforces them through the signed allowed_formats parameter. */
    public const array FORMATS = ['jpg', 'png', 'webp'];

    /** Largest accepted upload, checked in the browser and again on save from what Cloudinary stored. */
    public const int MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Cloudinary folder holding everything of one organisation (its logos, banners and every event's images).
     */
    public static function organizationRoot(int $organizationId): string
    {
        return self::root() . "/orgs/{$organizationId}";
    }

    /**
     * Cloudinary folder for an organisation's uploads of one kind (logos, gallery, flyers).
     */
    public static function organizationDirectory(int $organizationId, string $kind): string
    {
        return self::organizationRoot($organizationId) . "/{$kind}";
    }

    /**
     * Cloudinary folder for one event's uploads of one kind (banners, speakers, gallery, flyers).
     */
    public static function eventDirectory(int $organizationId, int $eventId, string $kind): string
    {
        return self::root() . "/orgs/{$organizationId}/events/{$eventId}/{$kind}";
    }

    /**
     * Where a logo uploaded during onboarding waits until its organisation exists; it is then moved into the
     * organisation's own folder. The key is random per onboarding form, so forms never share a folder.
     */
    public static function onboardingDirectory(string $formKey): string
    {
        return self::root() . '/onboarding/' . $formKey;
    }

    /**
     * URL for a stored value at a size; null when there is nothing to show (empty, unknown scheme, missing file).
     */
    public static function resolve(?string $value, ImagePreset|string|null $size = null): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $preset = ImagePreset::fromLegacy($size ?? ImagePreset::Hero);
        $image = CloudinaryImage::fromStored($value);

        if ($image !== null) {
            $client = app(CloudinaryClient::class);

            return $client->isConfigured() ? $image->url($client->cloudName(), $preset->maxWidth()) : null;
        }

        if (self::isExternal($value)) {
            return $value;
        }

        // Neither a link we accept nor a stored path (e.g. "javascript:…"): never rendered.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) === 1) {
            return null;
        }

        // Under the earlier Cloudinary disk every plain path is a Cloudinary image; one that is not a clean path
        // (e.g. "../x") is never rendered.
        if (LegacyCloudinaryPath::active()) {
            $legacy = LegacyCloudinaryPath::fromStored($value);
            $client = app(CloudinaryClient::class);

            return $legacy !== null && $client->isConfigured()
                ? $legacy->url($client->cloudName(), $preset->maxWidth())
                : null;
        }

        return self::legacyUrl($value);
    }

    /**
     * "url 400w, url 800w" for a Cloudinary image; null for links and old paths, which exist in one size only.
     */
    public static function srcset(?string $value, ImagePreset $preset): ?string
    {
        $image = CloudinaryImage::fromStored($value) ?? LegacyCloudinaryPath::fromStored($value);
        $client = app(CloudinaryClient::class);

        if ($image === null || !$client->isConfigured()) {
            return null;
        }

        return implode(', ', array_map(
            static fn(int $width): string => $image->url($client->cloudName(), $width) . ' ' . $width . 'w',
            $preset->widths(),
        ));
    }

    /**
     * Deletes an image we stored: the Cloudinary asset (and its CDN copies), or an old local upload. Pasted links
     * are left alone. A failure is logged, never shown: the page change the admin made has already been saved.
     */
    public static function deleteStored(?string $value): void
    {
        if ($value === null || trim($value) === '' || self::isExternal($value)) {
            return;
        }

        $image = CloudinaryImage::fromStored($value);

        // An unconverted image from the earlier Cloudinary disk is left in place (the convert command never deletes).
        if ($image === null && LegacyCloudinaryPath::fromStored($value) !== null) {
            return;
        }

        if ($image === null) {
            Storage::disk('public')->delete($value);

            return;
        }

        try {
            app(CloudinaryClient::class)->destroy($image->publicId);
        } catch (Throwable $exception) {
            Log::warning('Could not delete a replaced image on Cloudinary; it is now unused.', [
                'public_id' => $image->publicId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public static function isExternal(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        return Str::startsWith($value, ['http://', 'https://', '//', '/', 'data:']);
    }

    /**
     * Pasted links must be https: anything else would be mixed content or an unsafe scheme.
     */
    public static function isAcceptableLink(?string $value): bool
    {
        return (
            is_string($value)
            && Str::startsWith($value, 'https://')
            && filter_var($value, FILTER_VALIDATE_URL) !== false
        );
    }

    private static function root(): string
    {
        $environment = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) config('app.env', 'production')));

        return 'ticketlab/' . trim((string) $environment, '-');
    }

    private static function legacyUrl(string $path): ?string
    {
        $base = config('filesystems.legacy_media_url');

        if (is_string($base) && trim($base) !== '') {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }

        if (!Storage::disk('public')->exists($path)) {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        $relative = parse_url($url, PHP_URL_PATH);

        return is_string($relative) ? $relative : $url;
    }
}
