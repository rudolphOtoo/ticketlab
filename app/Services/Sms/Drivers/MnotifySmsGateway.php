<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * mNotify SMS driver for direct Ghanaian carrier routing (MTN, Telecel, AT).
 */
final class MnotifySmsGateway implements SmsGatewayInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $senderId,
        private readonly string $endpoint = 'https://api.mnotify.com/api/sms/quick',
        private readonly bool $enabled = true,
    ) {}

    public function send(string $to, string $message, array $context = []): bool
    {
        if (! $this->isEnabled() || blank($this->apiKey)) {
            return false;
        }

        try {
            $response = Http::timeout(10)->post("{$this->endpoint}?key={$this->apiKey}", [
                'recipient' => [$this->formatRecipient($to)],
                'sender' => substr($this->senderId, 0, 11),
                'message' => $message,
                'is_schedule' => false,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('mNotify SMS dispatch returned non-200 response', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'to' => $to,
            ]);

            return false;
        } catch (Throwable $e) {
            Log::error('mNotify SMS dispatch exception', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);

            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && filled($this->apiKey);
    }

    /**
     * Normalize local Ghana phone numbers (e.g. 0244123456 -> 233244123456).
     */
    private function formatRecipient(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
            return '233' . substr($cleaned, 1);
        }

        return $cleaned;
    }
}

