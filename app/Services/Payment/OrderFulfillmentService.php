<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Reservation\ReservationFulfilmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderFulfillmentService
{
    public function __construct(
        private readonly PaymentMatcher $matcher,
        private readonly ReservationFulfilmentService $reservations,
        private readonly AttendeeIssuer $attendeeIssuer,
        private readonly PaymentReviewService $paymentReview,
        private readonly PaymentGatewayMetadata $gatewayMetadata,
    ) {}

    /**
     * Mark a pending order as paid and issue one QR attendee pass per ticket.
     */
    public function fulfillReference(string $reference, array $payment = []): ?Order
    {
        $order = Order::query()->where('reference', $reference)->first();

        if ($order === null) {
            return null;
        }

        return $this->fulfill($order, $payment);
    }

    public function fulfill(Order $order, array $payment = []): Order
    {
        try {
            return DB::transaction(function () use ($order, $payment): Order {
                /** @var Order $order */
                $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

                if (in_array($order->payment_status, [OrderStatus::Paid, OrderStatus::RequiresReview], true)) {
                    return $order->fresh(['attendees']);
                }

                if ($order->payment_status !== OrderStatus::Pending) {
                    throw new OrderFulfillmentException('The order is not in a fulfillable state.');
                }

                $this->matcher->assertMatches($order, $payment);

                $metadata = $order->metadata ?? [];
                $fulfilled = $this->reservations->fulfill($order);
                $paidAt = now();

                $order->forceFill([
                    'reservation_id' => $fulfilled['reservation']->getKey(),
                    'payment_status' => OrderStatus::Paid,
                    'payment_method' => $this->matcher->paymentMethod(
                        $payment['channel'] ?? null,
                        $order->payment_method,
                    ),
                    'currency' => strtoupper((string) $payment['currency']),
                    'paid_at' => $paidAt,
                    'metadata' => array_merge($metadata, $this->gatewayMetadata->forPayment($payment, $paidAt)),
                ])->save();

                if (!$order->attendees()->exists()) {
                    $this->attendeeIssuer->issue($order, $fulfilled['ticket_type'], $fulfilled['quantity'], $metadata);
                }

                return $order->fresh(['attendees']);
            });
        } catch (Throwable $exception) {
            $this->paymentReview->mark($order, $exception->getMessage(), $payment);

            Log::critical('Payment fulfilment was blocked and requires manual review.', [
                'order_id' => $order->getKey(),
                'order_reference' => $order->reference,
                'reason' => $exception->getMessage(),
                'payment_reference' => $payment['reference'] ?? null,
                'payment_amount' => $payment['amount'] ?? null,
                'payment_currency' => $payment['currency'] ?? null,
            ]);

            throw $exception;
        }
    }
}
