<?php

declare(strict_types=1);

namespace App\Services\Media;

/**
 * The few Cloudinary calls TicketLab makes. Production uses SdkCloudinaryClient (the official cloudinary_php SDK);
 * tests bind Tests\Support\FakeCloudinaryClient, so the suite never reaches Cloudinary.
 */
interface CloudinaryClient
{
    /** False when CLOUDINARY_URL is not set: uploads are refused, pasted links still work. */
    public function isConfigured(): bool;

    /** The cloud name delivery URLs are built with (res.cloudinary.com/{cloud}/...). */
    public function cloudName(): string;

    public function apiKey(): string;

    /**
     * Signature for an upload the browser sends straight to Cloudinary. Every parameter signed here must be posted
     * back unchanged, or Cloudinary refuses the upload ("Invalid Signature").
     *
     * @param array<string, int|string> $parameters
     */
    public function sign(array $parameters): string;

    /** Whether Cloudinary itself produced this upload response (its signature over public_id and version). */
    public function verifyUploadResponse(string $publicId, int $version, string $signature): bool;

    /**
     * What Cloudinary stores for an image (Admin API), or null when there is no such image.
     *
     * @return array{bytes: int, format: string, version: int}|null
     */
    public function image(string $publicId): ?array;

    /**
     * Moves an image to a new public_id (e.g. from the onboarding folder into the new organisation's). Returns the
     * image's version afterwards, which the stored value must use.
     */
    public function rename(string $fromPublicId, string $toPublicId): int;

    /** Deletes an image and invalidates its cached copies on the CDN. */
    public function destroy(string $publicId): void;

    /**
     * Deletes every image under a folder (e.g. a deleted organisation's), then the folder and its sub-folders.
     * A folder that doesn't exist is not an error.
     */
    public function destroyFolder(string $folder): void;
}
