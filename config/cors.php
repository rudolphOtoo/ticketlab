<?php

declare(strict_types=1);

/*
 | Cross-Origin Resource Sharing.
 |
 | No route is called cross-origin from a browser: /api/qr is fetched by our own pages (same origin), the Paystack
 | webhook and the maintenance hooks (/api/run-scheduler, /api/run-migrate) are server-to-server. Laravel's default
 | ("api/*" for every origin) therefore only added "Access-Control-Allow-Origin: *" to machine endpoints. Add a path
 | here, with its real origins, only when a browser on another origin must call it.
 */

return [
    'paths' => [],

    'allowed_methods' => ['*'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
