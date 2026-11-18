<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\MediaUrl;
use GdImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Makes WebP copies of an uploaded image (thumb 200 px, medium 800 px, large 1600 px wide, never upscaled) on the
 * same disk, next to the original. Runs on the queue so uploads stay fast; pages serve the original until it is done.
 * GD images are freed automatically (imagedestroy() has been a no-op since PHP 8.0).
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    private const int WEBP_QUALITY = 80;

    public function __construct(
        public string $disk,
        public string $path,
    ) {}

    public function handle(): void
    {
        $storage = Storage::disk($this->disk);

        // Replaced or deleted before this job ran: nothing to do.
        if (!$storage->exists($this->path)) {
            return;
        }

        try {
            $source = imagecreatefromstring((string) $storage->get($this->path));
        } catch (Throwable) {
            // Laravel turns GD's "not a valid image" warning into an exception; treat it as undecodable.
            $source = false;
        }

        if (!$source instanceof GdImage) {
            Log::warning('Uploaded image could not be decoded for WebP variants.', [
                'disk' => $this->disk,
                'path' => $this->path,
            ]);

            return;
        }

        foreach (MediaUrl::SIZES as $size => $maxWidth) {
            $storage->put(MediaUrl::variantPath($this->path, $size), self::webp($source, $maxWidth), [
                'CacheControl' => MediaUrl::CACHE_CONTROL,
                'ContentType' => 'image/webp',
            ]);
        }

        MediaUrl::markVariantsReady($this->path);
    }

    private static function webp(GdImage $source, int $maxWidth): string
    {
        $width = imagesx($source);
        $image = $width > $maxWidth ? imagescale($source, $maxWidth) : $source;

        if (!$image instanceof GdImage) {
            $image = $source;
        }

        // Keep transparency from PNG/WebP sources.
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, self::WEBP_QUALITY);
        return (string) ob_get_clean();
    }
}
