<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Support\MediaUrl;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Signed direct uploads: the browser sends the file straight to Cloudinary, so no image passes through our
 * serverless function or its /tmp (which is not shared between requests or instances).
 *
 * 1. sign()   – our server signs the folder (one per organisation), a timestamp and the allowed formats.
 * 2. Browser  – posts the file to Cloudinary with those exact fields; Cloudinary refuses anything else.
 * 3. accept() – Livewire receives only public_id, version and Cloudinary's response signature. We check the
 *               signature, the folder and the stored size and format before the image is saved anywhere.
 */
final readonly class CloudinaryUploads
{
    public function __construct(
        private CloudinaryClient $client,
    ) {}

    /**
     * Everything the browser needs to upload one image into $folder.
     *
     * @return array{url: string, fields: array<string, string>, maxBytes: int, formats: list<string>}
     */
    public function sign(string $folder): array
    {
        if (!$this->client->isConfigured()) {
            throw ValidationException::withMessages([
                'upload' => 'Image uploads are not set up yet (CLOUDINARY_URL). Paste an https image link instead.',
            ]);
        }

        $parameters = [
            'allowed_formats' => implode(',', MediaUrl::FORMATS),
            'folder' => $folder,
            'timestamp' => (string) now()->getTimestamp(),
        ];

        return [
            'url' => 'https://api.cloudinary.com/v1_1/' . rawurlencode($this->client->cloudName()) . '/image/upload',
            'fields' => [
                ...$parameters,
                'api_key' => $this->client->apiKey(),
                'signature' => $this->client->sign($parameters),
            ],
            'maxBytes' => MediaUrl::MAX_BYTES,
            'formats' => MediaUrl::FORMATS,
        ];
    }

    /**
     * Checks an upload the browser reports and returns the value to store. Anything that fails is deleted from
     * Cloudinary (when it exists there) and refused with a message under $field.
     *
     * @throws ValidationException
     */
    public function accept(string $field, string $folder, string $publicId, int $version, string $signature): string
    {
        if (!$this->client->verifyUploadResponse($publicId, $version, $signature)) {
            Log::warning('Refused an image upload whose Cloudinary signature did not verify.', [
                'public_id' => $publicId,
            ]);

            throw ValidationException::withMessages([$field => 'That upload could not be verified. Please try again.']);
        }

        // Signed by Cloudinary, but it must also be in this organisation's (and event's) folder.
        if (!str_starts_with($publicId, rtrim($folder, '/') . '/')) {
            Log::warning('Refused an image upload from another folder.', [
                'public_id' => $publicId,
                'folder' => $folder,
            ]);

            throw ValidationException::withMessages([
                $field => 'That upload does not belong here. Please upload again.',
            ]);
        }

        $image = $this->client->image($publicId);

        if ($image === null) {
            throw ValidationException::withMessages([$field => 'That upload was not found. Please upload again.']);
        }

        if ($image['bytes'] > MediaUrl::MAX_BYTES || !in_array($image['format'], MediaUrl::FORMATS, true)) {
            $this->discard($publicId);

            throw ValidationException::withMessages([
                $field => 'Images must be JPG, PNG or WebP and at most 5 MB.',
            ]);
        }

        return new CloudinaryImage($publicId, $version)->toStored();
    }

    private function discard(string $publicId): void
    {
        try {
            $this->client->destroy($publicId);
        } catch (Throwable $exception) {
            Log::warning('Could not delete a refused upload on Cloudinary.', [
                'public_id' => $publicId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
