<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Media columns hold either an absolute/root-relative URL (legacy, pasted links) or a path on the media disk (uploads).
 *
 * Uploads live on the disk named by filesystems.media_disk: the local "public" disk in development, and a cloud disk
 * (S3, R2, ...) on hosts whose filesystem does not persist between requests, such as serverless deployments.
 */
final class MediaUrl
{
    public static function disk(): string
    {
        return (string) config('filesystems.media_disk', 'public');
    }

    /**
     * Where an event's uploads of one kind live, so every asset can be traced back to the event that owns it.
     */
    public static function eventDirectory(int $eventId, string $kind): string
    {
        return "events/{$eventId}/{$kind}";
    }

    public static function resolve(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (self::isExternal($path)) {
            return $path;
        }

        // Local files are served by this app: verify the file physically exists on disk.
        if (config('filesystems.disks.' . self::disk() . '.driver') === 'local') {
            if (! Storage::disk(self::disk())->exists($path)) {
                return null;
            }

            $url = Storage::disk(self::disk())->url($path);
            $relative = parse_url($url, PHP_URL_PATH);

            return is_string($relative) ? $relative : $url;
        }

        return Storage::disk(self::disk())->url($path);
    }

    /**
     * Persist an upload on the media disk and return its path.
     */
    public static function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::disk());

        if ($path === false) {
            throw new RuntimeException("Could not store the uploaded file in [{$directory}].");
        }

        return $path;
    }

    /**
     * Remove a previously uploaded file; external links are left alone.
     */
    public static function deleteStored(?string $path): void
    {
        if ($path !== null && $path !== '' && !self::isExternal($path)) {
            Storage::disk(self::disk())->delete($path);
        }
    }

    public static function isExternal(?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }

        return Str::startsWith($path, ['http://', 'https://', '//', '/', 'data:']);
    }
}
