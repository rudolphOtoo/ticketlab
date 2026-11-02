<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Payment\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Renders a real, scannable QR code for the Marketing Studio (canvas compositing and SVG/PNG downloads).
 */
class QrCodeController extends Controller
{
    public function __invoke(Request $request, QrCodeService $qrCodes): Response
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1024'],
            'format' => ['nullable', 'in:svg,png'],
            'fg' => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'bg' => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'margin' => ['nullable', 'integer', 'between:0,8'],
            'size' => ['nullable', 'integer', 'between:64,2048'],
        ]);

        $content = (string) $validated['content'];
        $foreground = '#' . ltrim((string) ($validated['fg'] ?? '000000'), '#');
        $background = '#' . ltrim((string) ($validated['bg'] ?? 'ffffff'), '#');
        $margin = (int) ($validated['margin'] ?? 2);
        $size = (int) ($validated['size'] ?? 512);

        $headers = ['Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff'];

        if (($validated['format'] ?? 'svg') === 'png') {
            // Scale is per module; ~25-33 modules for typical URLs, so this lands close to the requested pixel size.
            $scale = max(2, intdiv($size, 33));

            return response($qrCodes->png($content, $scale, $margin, $foreground, $background), 200, [
                'Content-Type' => 'image/png',
                ...$headers,
            ]);
        }

        return response($qrCodes->svg($content, $size, $margin, $foreground, $background), 200, [
            'Content-Type' => 'image/svg+xml',
            ...$headers,
        ]);
    }
}
