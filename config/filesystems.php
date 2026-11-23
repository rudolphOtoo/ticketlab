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
     | Supported drivers here: "local" (the S3 adapter is not installed).
     |
     */

    /*
     | Images are on Cloudinary (config/services.php, CLOUDINARY_URL); nothing in production writes to a disk.
     | Paths saved before Cloudinary ("orgs/1/logos/abc.png") are served from LEGACY_MEDIA_URL, the public address of
     | the bucket they were uploaded to, when set; otherwise from the local "public" disk (local development).
     */
    'legacy_media_url' => env('LEGACY_MEDIA_URL'),

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
