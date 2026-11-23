<?php

declare(strict_types=1);

namespace App\Services\Media;

use Cloudinary\Api\ApiUtils;
use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use RuntimeException;

/**
 * CloudinaryClient on the official SDK (cloudinary/cloudinary_php), configured only from CLOUDINARY_URL.
 */
final readonly class SdkCloudinaryClient implements CloudinaryClient
{
    private ?Configuration $configuration;

    public function __construct(?string $url)
    {
        $this->configuration = $url === null || trim($url) === '' ? null : new Configuration(trim($url));
    }

    public function isConfigured(): bool
    {
        return (
            $this->configuration !== null
            && (string) $this->configuration->cloud->cloudName !== ''
            && (string) $this->configuration->cloud->apiKey !== ''
            && (string) $this->configuration->cloud->apiSecret !== ''
        );
    }

    public function cloudName(): string
    {
        return (string) $this->configured()->cloud->cloudName;
    }

    public function apiKey(): string
    {
        return (string) $this->configured()->cloud->apiKey;
    }

    public function sign(array $parameters): string
    {
        return ApiUtils::signParameters($parameters, (string) $this->configured()->cloud->apiSecret);
    }

    public function verifyUploadResponse(string $publicId, int $version, string $signature): bool
    {
        // The SDK's SignatureVerifier reads its global configuration; this is the same check against ours.
        $expected = sha1(
            'public_id=' . $publicId . '&version=' . $version . (string) $this->configured()->cloud->apiSecret,
        );

        return hash_equals($expected, $signature);
    }

    public function image(string $publicId): ?array
    {
        try {
            $asset = $this->sdk()->adminApi()->asset($publicId, ['resource_type' => 'image']);
        } catch (NotFound) {
            return null;
        }

        return [
            'bytes' => (int) ($asset['bytes'] ?? 0),
            'format' => (string) ($asset['format'] ?? ''),
            'version' => (int) ($asset['version'] ?? 0),
        ];
    }

    public function rename(string $fromPublicId, string $toPublicId): int
    {
        $renamed = $this->sdk()->uploadApi()->rename($fromPublicId, $toPublicId, ['invalidate' => true]);

        return (int) ($renamed['version'] ?? 0);
    }

    public function destroy(string $publicId): void
    {
        $this->sdk()->uploadApi()->destroy($publicId, ['invalidate' => true]);
    }

    private function sdk(): Cloudinary
    {
        return new Cloudinary($this->configured());
    }

    private function configured(): Configuration
    {
        if (!$this->isConfigured() || $this->configuration === null) {
            throw new RuntimeException(
                'Image uploads need CLOUDINARY_URL (cloudinary://<api_key>:<api_secret>@<cloud_name>). '
                . 'See README "Media (Cloudinary)". Pasted https image links work without it.',
            );
        }

        return $this->configuration;
    }
}
