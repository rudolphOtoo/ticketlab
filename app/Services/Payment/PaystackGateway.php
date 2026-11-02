<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Thin server-side Paystack client used for callback
 * verification and signature-validated webhook fulfilment.
 */
class PaystackGateway
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

    /**
     * Open a transaction server-side so the amount and payout split are fixed before the buyer sees the popup.
     *
     * @param array<string, mixed> $transaction
     *
     * @return array{access_code: string, reference: string}
     */
    public function initialize(array $transaction): array
    {
        $secretKey = trim((string) config('services.paystack.secret_key', ''));

        if ($secretKey === '') {
            throw new PaystackCheckoutException('Online payments are not configured yet.');
        }

        $baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');

        try {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(
                    2,
                    250,
                    static fn(Throwable $exception): bool => $exception instanceof ConnectionException,
                    throw: false,
                )
                ->post($baseUrl . '/transaction/initialize', $transaction);
        } catch (ConnectionException $exception) {
            Log::error('Paystack transaction initialisation could not reach Paystack.', [
                'reference' => $transaction['reference'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            throw new PaystackCheckoutException('We could not reach Paystack. Please try again.', 0, $exception);
        }

        $message = (string) $response->json('message', '');
        $accessCode = $response->json('data.access_code');
        $reference = $response->json('data.reference');

        if ($response->successful() && is_string($accessCode) && is_string($reference)) {
            return ['access_code' => $accessCode, 'reference' => $reference];
        }

        if (str_contains(strtolower($message), 'duplicate transaction reference')) {
            throw PaystackCheckoutException::duplicateReference();
        }

        Log::error('Paystack refused to initialise a transaction.', [
            'reference' => $transaction['reference'] ?? null,
            'subaccount' => $transaction['subaccount'] ?? null,
            'http_status' => $response->status(),
            'paystack_message' => $message,
        ]);

        throw new PaystackCheckoutException('We could not start this payment. Please try again shortly.');
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

        $order = Order::findByGatewayReference($reference);

        if ($order === null) {
            // Rare path: the fulfilment service owns the unknown-reference alert.
            return $this->fulfillment->fulfillReference($reference, $data);
        }

        // Routed orders must prove where the money went; fetch the authoritative record if the event omits it.
        if ($order->payout_subaccount_code !== null && !array_key_exists('subaccount', $data)) {
            $data = $this->verify($reference);
        }

        return $this->fulfillment->fulfill($order, $data);
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
}
