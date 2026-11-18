<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Default Filesystem Disk
     |--------------------------------------------------------------------------
     |
     | Here you may specify the default filesystem disk that should be used
     | by the framework. The "local" disk, as well as a variety of cloud
     | based disks are available to your application for file storage.
     |
     */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
     |--------------------------------------------------------------------------
     | Filesystem Disks
     |--------------------------------------------------------------------------
     |
     | Below you may configure as many filesystem disks as necessary, and you
     | may even configure multiple disks for the same driver. Examples for
     | most supported storage drivers are configured here for reference.
     |
     | Supported drivers: "local", "ftp", "sftp", "s3"
     |
     */

    /*
     | Public media (organisation logos, event banners, speaker photos, gallery, flyers): "public" locally, "r2" in
     | production. Serverless hosts (Vercel) have no persistent disk, so production must never write media locally.
     */
    'media_disk' => env('MEDIA_DISK', 'public'),

    /*
     | Private files (ledger PDFs, Livewire's temporary uploads): "local" locally, "r2-private" in production. Served only
     | through short-lived signed links after an authorisation check, never by a public URL.
     */
    'private_disk' => env('PRIVATE_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/storage',
            'visibility' => 'public',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Cloudflare R2 (S3-compatible). The public bucket is served from MEDIA_URL (a custom domain on the bucket).
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_PUBLIC_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'url' => env('MEDIA_URL'),
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => true,
        ],

        // A separate private bucket: no public access, files reached only through temporary signed URLs.
        'r2-private' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_PRIVATE_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'vercel_temp' => [
            'driver' => 'local',
            'root' => '/tmp',
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Symbolic Links
     |--------------------------------------------------------------------------
     |
     | Here you may configure the symbolic links that will be created when the
     | `storage:link` Artisan command is executed. The array keys should be
     | the locations of the links and the values should be their targets.
     |
     */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
