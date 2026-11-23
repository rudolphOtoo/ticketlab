<?php

declare(strict_types=1);

namespace App\Services\Media;

/**
 * A Cloudinary image as stored in our media columns: "cloudinary:v{version}/{public_id}".
 *
 * Only the public_id and version are kept, never a delivery URL, so transformations (f_auto, q_auto, widths) are
 * chosen when the page is rendered. The version is part of every URL, so a replaced image never serves a stale copy.
 */
final readonly class CloudinaryImage
{
    public const string PREFIX = 'cloudinary:';

    /**
     * Letters, digits, "_", "-", "." and "/" (images converted from the earlier setup keep their extension in the
     * public_id, e.g. "ticketlab/logos/abc.jpg"); never a "." or ".." segment or an empty one.
     */
    private const string PATTERN = '#^cloudinary:v(\d{1,12})/((?!(?:[^/]*/)*\.{1,2}(?:/|$))[A-Za-z0-9_\-.]+(?:/[A-Za-z0-9_\-.]+)*)$#';

    public function __construct(
        public string $publicId,
        public int $version,
    ) {}

    public static function fromStored(?string $value): ?self
    {
        $matches = [];

        if ($value === null || preg_match(self::PATTERN, $value, $matches) !== 1 || strlen($matches[2]) > 255) {
            return null;
        }

        return new self($matches[2], (int) $matches[1]);
    }

    public static function isStored(?string $value): bool
    {
        return self::fromStored($value) !== null;
    }

    /** Whether a public_id can be stored (and later put into a URL as it is). */
    public static function isValidPublicId(string $publicId): bool
    {
        return self::fromStored(self::PREFIX . 'v1/' . $publicId) !== null;
    }

    public function toStored(): string
    {
        return self::PREFIX . 'v' . $this->version . '/' . $this->publicId;
    }

    /**
     * Delivery URL: automatic format and quality, scaled down to $width (c_limit never enlarges a smaller image).
     */
    public function url(string $cloudName, int $width): string
    {
        return sprintf(
            'https://res.cloudinary.com/%s/image/upload/f_auto,q_auto,c_limit,w_%d/v%d/%s',
            rawurlencode($cloudName),
            $width,
            $this->version,
            $this->publicId,
        );
    }
}
