<?php

declare(strict_types=1);

namespace App\Services\Payment;

use DateTimeInterface;

class PaymentGatewayMetadata
{
    /**
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    public function forPayment(array $payment, ?DateTimeInterface $verifiedAt = null): array
    {
        $amount = $payment['amount'] ?? null;
        $currency = $payment['currency'] ?? null;
        $fees = $payment['fees'] ?? null;

        $metadata = [
            'payment_reference' => $payment['reference'] ?? null,
            'gateway_amount_pesewas' => is_numeric($amount) ? (int) $amount : null,
            'gateway_currency' => is_string($currency) ? strtoupper($currency) : null,
            'payment_channel' => $payment['channel'] ?? null,
            'gateway_fee_pesewas' => is_numeric($fees) ? (int) $fees : null,
            'gateway_response' => $payment['gateway_response'] ?? null,
            'gateway_payload' => $payment,
        ];

        if ($verifiedAt !== null) {
            $metadata['payment_verified_at'] = $verifiedAt->format(DATE_ATOM);
            $metadata['fulfilled_at'] = $verifiedAt->format(DATE_ATOM);
        }

        return $metadata;
    }
}
