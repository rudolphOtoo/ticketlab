<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Temporary File Uploads
     |--------------------------------------------------------------------------
     |
     | Not used: no component takes Livewire file uploads (tests/Feature/Architecture/NoLivewireUploadsTest.php).
     | Images go from the browser straight to Cloudinary (App\Livewire\Concerns\UploadsImages), because a
     | serverless /tmp is not shared between requests or instances. Livewire's upload endpoint needs a signed URL
     | that only a WithFileUploads component can issue, so it accepts nothing.
     |
     */
    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['nullable', 'file', 'max:5120'],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
        'cleanup' => true,
    ],
];
