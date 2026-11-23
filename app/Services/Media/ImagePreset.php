<?php

declare(strict_types=1);

namespace App\Services\Media;

/**
 * How large an image is shown, and so which widths Cloudinary is asked for. The largest width is the plain src;
 * the others make the srcset, so a phone downloads a small copy and a wide desktop a sharp one.
 */
enum ImagePreset: string
{
    /** Organisation logos and speaker portraits (shown at 40 to 100 CSS pixels). */
    case Logo = 'logo';

    /** Event cards, gallery tiles and the admin previews (up to about 400 CSS pixels wide). */
    case Card = 'card';

    /** Event banners and full-width heroes. */
    case Hero = 'hero';

    /** @return non-empty-list<int> widths in pixels, smallest first */
    public function widths(): array
    {
        return match ($this) {
            self::Logo => [100, 200],
            self::Card => [400, 800],
            self::Hero => [640, 960, 1280, 1600],
        };
    }

    public function maxWidth(): int
    {
        return max($this->widths());
    }

    /** Default `sizes` hint for the srcset when the view does not give its own. */
    public function sizes(): string
    {
        return match ($this) {
            self::Logo => '100px',
            self::Card => '(min-width: 640px) 400px, 100vw',
            self::Hero => '(min-width: 1152px) 1152px, 100vw',
        };
    }

    /** The size names used before Cloudinary ("thumb", "medium", "large") still pick a sensible preset. */
    public static function fromLegacy(self|string $size): self
    {
        if ($size instanceof self) {
            return $size;
        }

        return match ($size) {
            'thumb', 'logo' => self::Logo,
            'medium', 'card' => self::Card,
            default => self::Hero,
        };
    }
}
