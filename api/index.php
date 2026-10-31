<?php

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Vercel Edge Fallback: Serve static assets generated inside the PHP Lambda
if ($uri !== '/' && ! str_contains($uri, '..')) {
    $publicPath = __DIR__ . '/../public' . $uri;
    if (file_exists($publicPath)) {
        $extension = pathinfo($publicPath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'json' => 'application/json',
            'ico' => 'image/x-icon',
        ];
        if (isset($mimeTypes[$extension])) {
            header('Content-Type: ' . $mimeTypes[$extension]);
            header('Cache-Control: public, max-age=31536000, immutable');
            readfile($publicPath);
            exit;
        }
    }
}

require __DIR__ . '/../public/index.php';
