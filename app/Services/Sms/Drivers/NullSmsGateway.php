<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsGatewayInterface;

/**
 * No-op driver used when SMS is disabled or during local development/tests.
 */
final class NullSmsGateway implements SmsGatewayInterface
{
    public function __construct(private readonly bool $enabled = false) {}

    public function send(string $to, string $message, array $context = []): bool
    {
        // Silently accepts without performing external network calls.
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}

