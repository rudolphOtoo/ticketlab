<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Private files (ledger PDFs): stored on filesystems.private_disk, "local" locally and the private R2 bucket in
 * production. They never get a public URL: callers check authorisation first, then hand out a short-lived signed link.
 */
final class PrivateFiles
{
    public const int LINK_MINUTES = 5;

    public static function disk(): string
    {
        return StorageDisks::assertUsable((string) config('filesystems.private_disk', 'local'), 'PRIVATE_DISK');
    }

    public static function put(string $path, string $contents, string $contentType): void
    {
        Storage::disk(self::disk())->put($path, $contents, ['ContentType' => $contentType]);
    }

    public static function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk(self::disk())->exists($path);
    }

    /**
     * A signed download link valid for LINK_MINUTES on cloud disks; null on a local disk (stream it instead).
     */
    public static function temporaryDownloadUrl(string $path, string $downloadName): ?string
    {
        if (config('filesystems.disks.' . self::disk() . '.driver') !== 's3') {
            return null;
        }

        return Storage::disk(self::disk())->temporaryUrl($path, now()->addMinutes(self::LINK_MINUTES), [
            'ResponseContentDisposition' => 'attachment; filename="' . addcslashes($downloadName, '"\\') . '"',
        ]);
    }
}
