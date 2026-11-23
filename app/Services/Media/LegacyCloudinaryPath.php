<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Support\MediaUrl;

/**
 * A media value stored by the earlier Cloudinary setup (the codebar-ag Flysystem disk, MEDIA_DISK=cloudinary): the
 * Laravel path, such as "logos/abc.jpg", with CLOUDINARY_FOLDER put in front of it on Cloudinary.
 *
 * That disk uploaded with public_id = the path (extension included) and folder = CLOUDINARY_FOLDER, and built its URLs
 * from "{folder}/{path}". Until `php artisan media:convert-legacy-cloudinary --execute` has rewritten these values to
 * "cloudinary:v{version}/{public_id}", MediaUrl serves them the same way, so nothing breaks on deploy day.
 *
 * Only active while MEDIA_DISK=cloudinary is still set (production's value under the earlier setup). Without it, plain
 * paths are the older R2/local uploads and MediaUrl serves them from LEGACY_MEDIA_URL.
 */
final readonly class LegacyCloudinaryPath
{
    /** Characters Laravel's generated file names and folder names use, plus spaces and brackets from original names. */
    private const string PATH = '#^[A-Za-z0-9_\-.() ]+(/[A-Za-z0-9_\-.() ]+)*$#';

    public function __construct(
        public string $path,
        public ?string $folder,
    ) {}

    /** Whether plain paths are still read as the earlier Cloudinary disk's (MEDIA_DISK=cloudinary). */
    public static function active(): bool
    {
        return config('services.cloudinary.legacy_disk') === 'cloudinary';
    }

    /**
     * The value as an earlier-setup path, or null when it is something else: empty, already converted, a link, a
     * scheme ("javascript:"), or a path that climbs out of its folder ("..").
     */
    public static function parse(?string $value, ?string $folder): ?self
    {
        if ($value === null || CloudinaryImage::isStored($value) || MediaUrl::isExternal($value)) {
            return null;
        }

        $path = trim(trim($value), '/');

        if (
            $path === ''
            || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1
            || preg_match(self::PATH, $path) !== 1
            || in_array('..', explode('/', $path), true)
            || in_array('.', explode('/', $path), true)
        ) {
            return null;
        }

        $folder = $folder === null ? '' : trim(trim($folder), '/');

        return new self($path, $folder === '' ? null : $folder);
    }

    /** The value as the earlier disk's path, when that disk is still active (see active()). */
    public static function fromStored(?string $value): ?self
    {
        if (!self::active()) {
            return null;
        }

        $folder = config('services.cloudinary.legacy_folder');

        return self::parse($value, is_string($folder) ? $folder : null);
    }

    /** The public_id the earlier disk's URLs pointed at: "{folder}/{path}" (CloudinaryPathNormalizer::prefixed). */
    public function deliveryId(): string
    {
        return $this->folder === null ? $this->path : $this->folder . '/' . $this->path;
    }

    /**
     * The URL the earlier disk built (the SDK's "/v1/{folder}/{path}", each segment URL-encoded), with our automatic
     * format and quality and a width limit.
     */
    public function url(string $cloudName, int $width): string
    {
        $segments = array_map(rawurlencode(...), explode('/', $this->deliveryId()));

        return sprintf(
            'https://res.cloudinary.com/%s/image/upload/f_auto,q_auto,c_limit,w_%d/v1/%s',
            rawurlencode($cloudName),
            $width,
            implode('/', $segments),
        );
    }

    /**
     * The public_ids this image may have on Cloudinary, most likely first. The disk sent the path with its extension
     * as public_id and the folder separately; accounts in "fixed folders" mode put the folder in front of the
     * public_id, accounts in "dynamic folders" mode do not. Without the extension covers images renamed by hand.
     *
     * @return list<string>
     */
    public function candidates(): array
    {
        $withoutExtension = (string) preg_replace('#\.[A-Za-z0-9]{1,5}$#', '', $this->path);
        $candidates = [];

        if ($this->folder !== null) {
            $candidates[] = $this->folder . '/' . $this->path;
            $candidates[] = $this->folder . '/' . $withoutExtension;
        }

        $candidates[] = $this->path;
        $candidates[] = $withoutExtension;

        return array_values(array_unique(array_filter($candidates, static fn(string $id): bool => $id !== '')));
    }
}
