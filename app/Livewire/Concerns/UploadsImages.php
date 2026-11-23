<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Services\Media\CloudinaryUploads;
use App\Support\MediaUrl;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Signed direct image uploads for a Livewire component (see CloudinaryUploads and resources/js/media/image-upload.js).
 *
 * An accepted upload waits in $pendingImages under its field name until the form is saved. The array is #[Locked]
 * and only acceptImageUpload() adds to it, so a browser cannot point a field at an image that was never checked.
 * imageUploadFolder() says which fields the component takes and where their files go.
 */
trait UploadsImages
{
    /** @var array<string, string> field => checked Cloudinary value, until the form is saved */
    #[Locked]
    public array $pendingImages = [];

    /**
     * Cloudinary folder for an upload field, or null when this component has no such field.
     */
    abstract protected function imageUploadFolder(string $field): ?string;

    /**
     * Called from the browser before it uploads: the signed fields for one image.
     *
     * @return array{url: string, fields: array<string, string>, maxBytes: int, formats: list<string>}
     */
    public function signImageUpload(string $field): array
    {
        try {
            return app(CloudinaryUploads::class)->sign($this->uploadFolderFor($field));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([$field => $exception->validator->errors()->first()]);
        }
    }

    /**
     * Called from the browser after Cloudinary accepted the file. Holds the checked image until the form is saved.
     */
    public function acceptImageUpload(string $field, string $publicId, int $version, string $signature): void
    {
        $stored = app(CloudinaryUploads::class)->accept(
            $field,
            $this->uploadFolderFor($field),
            $publicId,
            $version,
            $signature,
        );

        // A second upload before saving replaces the first, which would otherwise never be used or deleted.
        $this->discardPendingImage($field);
        $this->pendingImages[$field] = $stored;
        $this->resetValidation($field);
    }

    /**
     * The checked upload waiting for this field, if any.
     */
    public function pendingImage(string $field): ?string
    {
        return $this->pendingImages[$field] ?? null;
    }

    /**
     * The form was saved with this upload: it is no longer pending, so closing the form keeps it.
     */
    protected function pendingImageSaved(string $field): void
    {
        unset($this->pendingImages[$field]);
    }

    /**
     * Deletes an upload that will not be saved (the form was cancelled or the upload replaced).
     */
    protected function discardPendingImage(string $field): void
    {
        MediaUrl::deleteStored($this->pendingImage($field));
        unset($this->pendingImages[$field]);
    }

    private function uploadFolderFor(string $field): string
    {
        return (
            $this->imageUploadFolder($field) ?? throw ValidationException::withMessages([
                $field => 'This form does not take that upload.',
            ])
        );
    }
}
