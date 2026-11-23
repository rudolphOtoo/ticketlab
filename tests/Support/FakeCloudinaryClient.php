<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Media\CloudinaryClient;
use RuntimeException;

/**
 * Stands in for Cloudinary in every test (bound in tests/Pest.php). It signs and verifies exactly as Cloudinary
 * does (SHA-1 of the parameters plus a secret), keeps uploaded images in memory, and records deletes and renames.
 */
final class FakeCloudinaryClient implements CloudinaryClient
{
    public const string CLOUD = 'demo-cloud';

    public const string API_KEY = '111222333';

    private const string SECRET = 'fake-cloudinary-secret';

    public bool $configured = true;

    /** @var array<string, array{bytes: int, format: string, version: int}> public_id => what Cloudinary holds */
    public array $images = [];

    /** @var list<string> */
    public array $destroyed = [];

    /** @var list<string> */
    public array $destroyedFolders = [];

    /** @var list<array{from: string, to: string}> */
    public array $renamed = [];

    /** @var list<array<string, int|string>> every parameter set signed for an upload */
    public array $signed = [];

    /**
     * What the browser receives from Cloudinary after uploading into $folder: the image now exists here.
     *
     * @return array{public_id: string, version: int, signature: string}
     */
    public function upload(string $folder, int $bytes = 120_000, string $format = 'jpg', ?string $name = null): array
    {
        $publicId = rtrim($folder, '/') . '/' . ($name ?? 'img' . (count($this->images) + 1));
        $version = 1_700_000_000 + count($this->images);
        $this->images[$publicId] = ['bytes' => $bytes, 'format' => $format, 'version' => $version];

        return [
            'public_id' => $publicId,
            'version' => $version,
            'signature' => $this->responseSignature($publicId, $version),
        ];
    }

    /**
     * An image that already exists on Cloudinary under $publicId, e.g. one stored by the earlier Flysystem disk.
     */
    public function seed(string $publicId, int $version = 1_650_000_000, string $format = 'jpg'): void
    {
        $this->images[$publicId] = ['bytes' => 90_000, 'format' => $format, 'version' => $version];
    }

    public function responseSignature(string $publicId, int $version): string
    {
        return sha1('public_id=' . $publicId . '&version=' . $version . self::SECRET);
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function cloudName(): string
    {
        return self::CLOUD;
    }

    public function apiKey(): string
    {
        return self::API_KEY;
    }

    public function sign(array $parameters): string
    {
        $this->signed[] = $parameters;
        ksort($parameters);

        return sha1(urldecode(http_build_query($parameters)) . self::SECRET);
    }

    public function verifyUploadResponse(string $publicId, int $version, string $signature): bool
    {
        return hash_equals($this->responseSignature($publicId, $version), $signature);
    }

    public function image(string $publicId): ?array
    {
        $image = $this->images[$publicId] ?? null;

        return (
            $image === null
                ? null
                : ['bytes' => $image['bytes'], 'format' => $image['format'], 'version' => $image['version']]
        );
    }

    public function rename(string $fromPublicId, string $toPublicId): int
    {
        $image = $this->images[$fromPublicId] ?? throw new RuntimeException("No image {$fromPublicId}.");
        unset($this->images[$fromPublicId]);
        $image['version']++;
        $this->images[$toPublicId] = $image;
        $this->renamed[] = ['from' => $fromPublicId, 'to' => $toPublicId];

        return $image['version'];
    }

    public function destroy(string $publicId): void
    {
        unset($this->images[$publicId]);
        $this->destroyed[] = $publicId;
    }

    public function destroyFolder(string $folder): void
    {
        $prefix = trim($folder, '/') . '/';
        $this->images = array_filter(
            $this->images,
            static fn(string $publicId): bool => !str_starts_with($publicId, $prefix),
            ARRAY_FILTER_USE_KEY,
        );
        $this->destroyedFolders[] = trim($folder, '/');
    }
}
