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
        // Temporary uploads are never public: they go to the private disk (the private R2 bucket in production).
        'disk' => env('LIVEWIRE_DISK', env('PRIVATE_DISK', 'local')),
        'rules' => ['nullable', 'file', 'max:5120'],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
        'cleanup' => true,
    ],
];
