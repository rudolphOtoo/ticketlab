<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Checks that a cloud (S3/R2) disk is fully configured before it is used, so a missing setting fails with a message
 * naming the environment variables to set, instead of a TypeError deep inside the storage driver.
 */
final class StorageDisks
{
    /** Disk config key → environment variable an operator sets, for the R2 disks defined in config/filesystems.php. */
    private const array REQUIRED = [
        'r2' => [
            'key' => 'R2_ACCESS_KEY_ID',
            'secret' => 'R2_SECRET_ACCESS_KEY',
            'endpoint' => 'R2_ENDPOINT',
            'bucket' => 'R2_PUBLIC_BUCKET',
            'url' => 'MEDIA_URL',
        ],
        'r2-private' => [
            'key' => 'R2_ACCESS_KEY_ID',
            'secret' => 'R2_SECRET_ACCESS_KEY',
            'endpoint' => 'R2_ENDPOINT',
            'bucket' => 'R2_PRIVATE_BUCKET',
        ],
    ];

    /** @var array<string, true> Disks already checked in this request. */
    private static array $checked = [];

    /**
     * @param string $setting The setting that selected the disk (e.g. "MEDIA_DISK"), named in the error.
     */
    public static function assertUsable(string $disk, string $setting): string
    {
        if (array_key_exists($disk, self::$checked)) {
            return $disk;
        }

        $config = config("filesystems.disks.{$disk}");

        if (!is_array($config)) {
            throw new RuntimeException(
                "{$setting}={$disk}, but no disk named \"{$disk}\" is configured in config/filesystems.php.",
            );
        }

        if (($config['driver'] ?? null) === 's3') {
            $missing = array_values(array_filter(
                self::REQUIRED[$disk] ?? ['key' => 'key', 'secret' => 'secret', 'bucket' => 'bucket'],
                static fn(string $env, string $key): bool => (
                    !is_string($config[$key] ?? null)
                    || trim($config[$key]) === ''
                ),
                ARRAY_FILTER_USE_BOTH,
            ));

            if ($missing !== []) {
                throw new RuntimeException(
                    "{$setting}={$disk}, but these settings are empty: "
                    . implode(', ', $missing)
                    . '. Set them (see README "Media storage"), or use '
                    . ($setting === 'MEDIA_DISK' ? 'MEDIA_DISK=public' : 'PRIVATE_DISK=local')
                    . ' for local development.',
                );
            }
        }

        self::$checked[$disk] = true;

        return $disk;
    }
}
