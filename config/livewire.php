<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | Livewire handles file uploads by storing them in a temporary directory
    | before the form is submitted. In serverless / multi-instance deployments,
    | point this to a shared cloud disk (S3, Cloudflare R2) via MEDIA_DISK.
    |
    */
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_DISK', env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local'))),
        'rules' => ['nullable', 'file', 'max:12288'],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
        'cleanup' => true,
    ],
];
