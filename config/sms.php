<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Service Master Toggle
    |--------------------------------------------------------------------------
    |
    | Disabled by default. When set to true in .env (SMS_ENABLED=true),
    | TicketLab will automatically dispatch queued SMS notifications
    | upon ticket issuance and broadcast announcements.
    |
    */

    'enabled' => (bool) env('SMS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "null", "log", "mnotify", "twilio"
    |
    */

    'default' => env('SMS_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Default Sender ID
    |--------------------------------------------------------------------------
    |
    | The approved alphanumeric sender header (max 11 chars on GSM networks).
    |
    */

    'sender_id' => env('SMS_SENDER_ID', 'TicketLab'),

    /*
    |--------------------------------------------------------------------------
    | Driver Configurations
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'null' => [],

        'log' => [
            'channel' => env('SMS_LOG_CHANNEL', 'single'),
        ],

        'mnotify' => [
            'api_key' => env('MNOTIFY_API_KEY', ''),
            'endpoint' => env('MNOTIFY_ENDPOINT', 'https://api.mnotify.com/api/sms/quick'),
        ],

        'twilio' => [
            'sid' => env('TWILIO_SID', ''),
            'token' => env('TWILIO_AUTH_TOKEN', ''),
            'from' => env('TWILIO_FROM', ''),
        ],

    ],

];

