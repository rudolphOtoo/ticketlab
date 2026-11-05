<?php

declare(strict_types=1);

// Normalize SCRIPT_NAME and SCRIPT_FILENAME so Laravel's Symfony Request base URL
// resolves to root ("") instead of "/api", fixing route resolution and URL generation on Vercel serverless.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

// Serverless Cache Isolation: ensure package and service manifests resolve to writable ephemeral storage.
putenv('APP_PACKAGES_CACHE=/tmp/storage/bootstrap/cache/packages.php');
putenv('APP_SERVICES_CACHE=/tmp/storage/bootstrap/cache/services.php');
$_ENV['APP_PACKAGES_CACHE'] = '/tmp/storage/bootstrap/cache/packages.php';
$_ENV['APP_SERVICES_CACHE'] = '/tmp/storage/bootstrap/cache/services.php';
$_SERVER['APP_PACKAGES_CACHE'] = '/tmp/storage/bootstrap/cache/packages.php';
$_SERVER['APP_SERVICES_CACHE'] = '/tmp/storage/bootstrap/cache/services.php';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Vercel Serverless Storage Fallback: Serve uploaded media from /tmp/storage/app/public
if (str_starts_with($uri, '/storage/') && ! str_contains($uri, '..')) {
    $storageFile = '/tmp/storage/app/public/' . substr($uri, 9);
    if (file_exists($storageFile)) {
        $extension = strtolower(pathinfo($storageFile, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'avif' => 'image/avif',
            'pdf' => 'application/pdf',
        ];
        if (isset($mimeTypes[$extension])) {
            header('Content-Type: ' . $mimeTypes[$extension]);
            header('Cache-Control: public, max-age=86400');
            readfile($storageFile);
            exit;
        }
    }
}

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

