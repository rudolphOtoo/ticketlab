<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Default Mailer
     |--------------------------------------------------------------------------
     |
     | This option controls the default mailer that is used to send all email
     | messages unless another mailer is explicitly specified when sending
     | the message. All additional mailers can be configured within the
     | "mailers" array. Examples of each type of mailer are provided.
     |
     */

    'default' => env('MAIL_MAILER', 'log'),

    /*
     |--------------------------------------------------------------------------
     | Mailer Configurations
     |--------------------------------------------------------------------------
     |
     | Here you may configure all of the mailers used by your application plus
     | their respective settings. Several examples have been configured for
     | you and you are free to add your own as your application requires.
     |
     | Laravel supports a variety of mail "transport" drivers that can be used
     | when delivering an email. You may specify which one you're using for
     | your mailers below. You may also add additional mailers if needed.
     |
     | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
     |            "postmark", "resend", "log", "array",
     |            "failover", "roundrobin"
     |
     */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            // Short, so a slow or unreachable relay fails fast inside the serverless request and the retry takes over.
            'timeout' => (int) env('MAIL_TIMEOUT', 8),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(
                (string) env('APP_URL', 'http://localhost'),
                PHP_URL_HOST,
            )),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Global "From" Address
     |--------------------------------------------------------------------------
     |
     | You may wish for all emails sent by your application to be sent from
     | the same address. Here you may specify a name and address that is
     | used globally for all emails that are sent by your application.
     |
     */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

    /*
     |--------------------------------------------------------------------------
     | Daily sending cap (Brevo)
     |--------------------------------------------------------------------------
     |
     | Brevo's free plan sends 300 emails a day across every kind of email, reset daily, with no rollover; Starter
     | removes the cap (set a large BREVO_DAILY_LIMIT). Every email we send takes one slot of today's count
     | (App\Services\Mail\MailQuota), and each kind may only take a slot while today's total is under its share of
     | the cap (defaults below, each settable from the environment):
     |
     |   transactional 100%  (300)   organisation-admin invitations, payout alerts, contact-form enquiries
     |   ticket         90%  (270)   the buyer's passes (the ticket page and PDF are the main ticket)
     |   zone_choice    70%  (210)   "choose your zone" requests
     |   broadcast      50%  (150)   Broadcast Hub messages, spread over the following days when larger
     |
     | Tickets get room before zone-choice requests and broadcasts, but stop at 90%: on a big ticket day the last 30
     | slots stay free, so an admin invitation or a payout alert can still go out the same day.
     |
     | An email without room waits for the next day (it is never dropped). Emails Laravel sends itself (password
     | resets, verification links) are never held back, but they are counted.
     |
     */

    'quota' => [
        'daily_limit' => (int) env('BREVO_DAILY_LIMIT', 300),

        // Brevo resets the count at midnight in the account's timezone (Ghana: UTC).
        'timezone' => env('MAIL_QUOTA_TIMEZONE', 'UTC'),

        // Percent of the daily limit each kind may use, counted against everything sent today.
        'shares' => [
            'transactional' => (int) env('MAIL_SHARE_TRANSACTIONAL', 100),
            'ticket' => (int) env('MAIL_SHARE_TICKET', 90),
            'zone_choice' => (int) env('MAIL_SHARE_ZONE_CHOICE', 70),
            'broadcast' => (int) env('MAIL_SHARE_BROADCAST', 50),
        ],

        // The platform dashboard turns amber from this share of the limit.
        'warn_percent' => 80,
    ],
];
