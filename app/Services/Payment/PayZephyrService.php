<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin server-side Paystack/PayZephyr handshake used for callback
 * verification and signature-validated webhook fulfilment.
 */
class PayZephyrService
{
    public function __construct(
        private readonly OrderFulfillmentService $fulfillment,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function verify(string $reference): array
    {
        $secretKey = trim((string) config('services.paystack.secret_key', ''));

        if ($secretKey === '') {
            throw new RuntimeException('The Paystack secret key has not been configured.');
        }

        $baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');

        $response = Http::withToken($secretKey)->acceptJson()->get(
            $baseUrl . '/transaction/verify/' . rawurlencode($reference),
        );

        $response->throw();

        $data = $response->json('data');

        if (!is_array($data)) {
            return [];
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function verifyAndFulfill(string $reference): ?Order
    {
        $data = $this->verify($reference);

        if (!$this->isSuccessful($data)) {
            return null;
        }

        return $this->fulfillment->fulfillReference($reference, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function handleChargeSuccess(array $data): ?Order
    {
        if (!$this->isSuccessful($data)) {
            return null;
        }

        $reference = $data['reference'] ?? null;

        if (!is_string($reference) || $reference === '') {
            return null;
        }

        return $this->fulfillment->fulfillReference($reference, $data);
    }

    public function verifySignature(string $payload, ?string $signature): bool
    {
        $secretKey = trim((string) config('services.paystack.secret_key', ''));

        if ($secretKey === '') {
            Log::critical('Paystack webhook signature verification is disabled because no secret key is configured.');

            return false;
        }

        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secretKey), $signature);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function isSuccessful(array $data): bool
    {
        return ($data['status'] ?? null) === 'success';
    }

    public function publicKey(): string
    {
        return (string) config('services.paystack.public_key', '');
    }
}
