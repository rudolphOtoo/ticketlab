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

    private const string PATTERN = '#^cloudinary:v(\d{1,12})/([A-Za-z0-9_\-/]{1,255})$#';

    public function __construct(
        public string $publicId,
        public int $version,
    ) {}

    public static function fromStored(?string $value): ?self
    {
        $matches = [];

        if ($value === null || preg_match(self::PATTERN, $value, $matches) !== 1) {
            return null;
        }

        return new self($matches[2], (int) $matches[1]);
    }

    public static function isStored(?string $value): bool
    {
        return self::fromStored($value) !== null;
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
