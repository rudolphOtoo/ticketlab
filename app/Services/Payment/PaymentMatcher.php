<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Services\PaystackFeeCalculator;

class PaymentMatcher
{
    /**
     * @param array<string, mixed> $payment
     */
    public function assertMatches(Order $order, array $payment): void
    {
        $reference = $payment['reference'] ?? null;

        if (!is_string($reference) || $reference === '' || !hash_equals($order->gatewayReference(), $reference)) {
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
        $expectedCurrency = strtoupper($order->currency);

        if ($currency === '' || $currency !== $expectedCurrency) {
            throw new OrderFulfillmentException('The payment currency does not match the order currency.');
        }

        $this->assertRouting($order, $payment);
    }

    /**
     * Revenue for a zone must have settled into that zone's subaccount, at exactly the ticket value.
     *
     * @param array<string, mixed> $payment
     */
    private function assertRouting(Order $order, array $payment): void
    {
        $expected = $order->payout_subaccount_code;

        if ($expected === null && !array_key_exists('subaccount', $payment)) {
            return;
        }

        $subaccount = $payment['subaccount'] ?? null;
        $actual = is_array($subaccount) ? $subaccount['subaccount_code'] ?? null : null;

        if ($actual !== $expected) {
            throw new OrderFulfillmentException('The payment settled to a different payout account than this order.');
        }

        $share = $payment['fees_split']['subaccount'] ?? null;

        if (
            $expected !== null
            && is_numeric($share)
            && (int) $share !== PaystackFeeCalculator::toPesewas($order->net_amount)
        ) {
            throw new OrderFulfillmentException('The zone payout share does not match the ticket value.');
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
