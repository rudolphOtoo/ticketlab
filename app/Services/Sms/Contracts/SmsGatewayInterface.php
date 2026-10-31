<?php

declare(strict_types=1);

namespace App\Services\Sms\Contracts;

/**
 * Universal contract for plug-and-play SMS gateways.
 */
interface SmsGatewayInterface
{
    /**
     * Send an SMS message to a mobile number.
     *
     * @param  string  $to  E.164 or local phone number (e.g. 0244123456 or +233244123456)
     * @param  string  $message  Text body to deliver
     * @param  array<string, mixed>  $context  Optional metadata (order reference, event id, etc.)
     * @return bool True if successfully dispatched or gracefully skipped
     */
    public function send(string $to, string $message, array $context = []): bool;

    /**
     * Whether this gateway driver is currently enabled and configured.
     */
    public function isEnabled(): bool;
}

