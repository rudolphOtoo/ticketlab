<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * Log driver: writes dispatches to the application log for testing and verification.
 */
final class LogSmsGateway implements SmsGatewayInterface
{
    public function __construct(
        private readonly string $channel = 'single',
        private readonly bool $enabled = true,
    ) {}

    public function send(string $to, string $message, array $context = []): bool
    {
        Log::channel($this->channel)->info("SMS dispatched to [{$to}]: {$message}", $context);

        return true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}

