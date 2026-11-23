<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Content-Security-Policy
 |--------------------------------------------------------------------------
 |
 | Off by default. CSP_MODE=report-only sends the policy without blocking anything (violations appear in the
 | browser console), which is the safe way to try it on a preview deploy; CSP_MODE=enforce blocks.
 |
 | Everything the pages legitimately load is listed here, so this is the one place to edit when adding a service:
 | - Cloudinary: images from res.cloudinary.com, browser uploads to the upload endpoint (services.cloudinary).
 | - Paystack's inline checkout: its script, its API and its popup frame.
 | - Pasted image links may be on any https host, so img-src allows https: as a whole.
 | - Livewire and Alpine evaluate expressions from attributes, so script-src still needs 'unsafe-eval', and the
 |   views have small inline scripts ('unsafe-inline'). Tightening those (nonces, Alpine's CSP build) is later work.
 | The Vite dev server is added automatically while it is running (local development only).
 |
 */

return [
    'mode' => env('CSP_MODE', 'off'),

    'directives' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://js.paystack.co'],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'blob:', 'https://res.cloudinary.com', 'https:'],
        'font-src' => ["'self'", 'data:'],
        'connect-src' => ["'self'", 'https://api.paystack.co'],
        'frame-src' => ['https://checkout.paystack.com', 'https://*.paystack.co'],
        'media-src' => ["'self'", 'blob:'],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'self'"],
    ],
];
