<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Media columns hold either an absolute/root-relative URL (legacy, pasted links) or a path on the public disk (uploads).
 */
final class MediaUrl
{
    public const string DISK = 'public';

    public static function resolve(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (self::isExternal($path)) {
            return $path;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * Persist an upload on the public disk and return its path.
     */
    public static function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::DISK);

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
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private static function isExternal(string $path): bool
    {
        return Str::startsWith($path, ['http://', 'https://', '//', '/', 'data:']);
    }
}
