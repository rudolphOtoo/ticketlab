<?php

declare(strict_types=1);

namespace App\Services\Payment;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;

class QrCodeService
{
    /**
     * Render a QR payload as an inline SVG for mobile ticket passes and vector downloads.
     */
    public function svg(
        string $content,
        int $size = 240,
        int $margin = 1,
        string $foreground = '#000000',
        string $background = '#ffffff',
    ): string {
        $svg = (string) new QRCode($this->options(QRMarkupSVG::class, $margin, [
            'svgAddXmlHeader' => false,
            'moduleValues' => $this->moduleValues($foreground, $background),
        ]))->render($content);

        // Give the vector an intrinsic size so it renders consistently inside HTML documents and canvases.
        return (string) preg_replace('/^<svg /', sprintf('<svg width="%1$d" height="%1$d" ', $size), trim($svg), 1);
    }

    /**
     * Render a QR payload as raw PNG bytes (DomPDF and canvas friendly).
     */
    public function png(
        string $content,
        int $scale = 10,
        int $margin = 2,
        string $foreground = '#000000',
        string $background = '#ffffff',
    ): string {
        return (string) new QRCode($this->options(QRGdImagePNG::class, $margin, [
            'scale' => $scale,
            'bgColor' => $this->rgb($background),
            'moduleValues' => $this->moduleValues($this->rgb($foreground), $this->rgb($background)),
        ]))->render($content);
    }

    public function pngDataUri(string $content, int $scale = 10, int $margin = 2): string
    {
        return 'data:image/png;base64,' . base64_encode($this->png($content, $scale, $margin));
    }

    /**
     * @param class-string<QROutputInterface> $outputInterface
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function options(string $outputInterface, int $margin, array $overrides): array
    {
        return [
            'outputInterface' => $outputInterface,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => $margin > 0,
            'quietzoneSize' => max(0, $margin),
            ...$overrides,
        ];
    }

    /**
     * @template TValue
     *
     * @param TValue $dark
     * @param TValue $light
     *
     * @return array<int, TValue>
     */
    private function moduleValues(mixed $dark, mixed $light): array
    {
        $values = [];

        foreach (QROutputInterface::DEFAULT_MODULE_VALUES as $moduleType => $isDark) {
            $values[$moduleType] = $isDark ? $dark : $light;
        }

        return $values;
    }

    /**
     * @return array{int, int, int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
