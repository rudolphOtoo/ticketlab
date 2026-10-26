<?php

declare(strict_types=1);

namespace App\Services\Payment;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    /**
     * Render a QR payload as an inline SVG for mobile ticket passes.
     */
    public function svg(string $content, int $size = 240, int $margin = 1): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd());

        $svg = new Writer($renderer)->writeString($content);

        // The generator prefixes an XML declaration that is not needed when
        // the SVG is embedded directly inside the ticket HTML document.
        return trim((string) preg_replace('/<\?xml[^>]*\?>/', '', $svg));
    }
}
