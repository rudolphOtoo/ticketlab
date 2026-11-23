<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Third Party Services
     |--------------------------------------------------------------------------
     |
     | This file is for storing the credentials for third party services such
     | as Resend, Postmark, AWS, and more. This file provides the de facto
     | location for this type of information, allowing packages to have
     | a conventional file to locate the various service credentials.
     |
     */
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        // Every paid order is split to its zone's verified Paystack subaccount. There is no switch and no fallback
        // to the main account: a zone without a verified subaccount cannot sell paid tickets.
    ],

    // Public images (logos, banners, speaker photos, gallery, flyer backgrounds). One setting:
    // cloudinary://<api_key>:<api_secret>@<cloud_name>. Unset, uploads are refused with a clear message and
    // pasted https links still work. Assets live under ticketlab/{APP_ENV}/orgs/{org_id}/...
    'cloudinary' => [
        'url' => env('CLOUDINARY_URL'),
        // Where browsers post uploads. Only tests change it (to a local stand-in); the CSP allows exactly this origin.
        'upload_base' => env('CLOUDINARY_UPLOAD_BASE', 'https://api.cloudinary.com'),
    ],

    // Shared secret for the serverless cron and migration endpoints; both refuse every request while it is unset.
    'scheduler' => [
        'token' => env('SCHEDULER_TOKEN'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
];
