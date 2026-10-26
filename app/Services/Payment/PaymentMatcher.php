<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Models\Order;

class PaymentMatcher
{
    /**
     * @param array<string, mixed> $payment
     */
    public function assertMatches(Order $order, array $payment): void
    {
        $reference = $payment['reference'] ?? null;

        if (!is_string($reference) || $reference === '' || !hash_equals($order->reference, $reference)) {
            throw new OrderFulfillmentException('The payment reference does not match this order.');
        }

        if (($payment['status'] ?? null) !== 'success') {
            throw new OrderFulfillmentException('The payment has not completed successfully.');
        }

        $amount = $payment['amount'] ?? null;

        if (!is_numeric($amount) || (int) $amount !== $order->gross_pesewas) {
            throw new OrderFulfillmentException('The paid amount does not match the order total.');
        }

        $currency = strtoupper(trim((string) ($payment['currency'] ?? '')));
        $expectedCurrency = strtoupper((string) ($order->currency ?? 'GHS'));

        if ($currency === '' || $currency !== $expectedCurrency) {
            throw new OrderFulfillmentException('The payment currency does not match the order currency.');
        }
    }

    public function paymentMethod(mixed $channel, PaymentMethod $fallback): PaymentMethod
    {
        return match ($channel) {
            'mobile_money' => PaymentMethod::MobileMoney,
            'card' => PaymentMethod::Card,
            default => $fallback,
        };
    }
}
