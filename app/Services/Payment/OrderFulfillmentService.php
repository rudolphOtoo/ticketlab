<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Jobs\SendTicketEmail;
use App\Jobs\SendTicketSms;
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
     *
     * @param array<string, mixed> $payment
     */
    public function fulfillReference(string $reference, array $payment = []): ?Order
    {
        $order = Order::findByGatewayReference($reference);

        if ($order === null) {
            Log::warning('A successful payment arrived for an unknown order reference.', [
                'payment_reference' => $reference,
                'payment_amount' => $payment['amount'] ?? null,
            ]);

            return null;
        }

        return $this->fulfill($order, $payment);
    }

    /**
     * @param array<string, mixed> $payment
     */
    public function fulfill(Order $order, array $payment = []): Order
    {
        $passesIssued = false;

        try {
            $fulfilled = DB::transaction(function () use ($order, $payment, &$passesIssued): Order {
                $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

                if (in_array($order->payment_status, [OrderStatus::Paid, OrderStatus::RequiresReview], true)) {
                    $this->flagSecondPayment($order, $payment);

                    return $order->load('attendees');
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
                    $passesIssued = true;
                }

                return $order->refresh()->load('attendees');
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

        // Notifications happen after commit and outside the review guard: an outage must never flag a paid order.
        if ($passesIssued) {
            SendTicketEmail::dispatch($fulfilled->id)->onQueue('transactional');
            SendTicketSms::dispatchIf((bool) config('sms.enabled', false), $fulfilled->id);
        }

        return $fulfilled;
    }

    /**
     * Webhook retries repeat the settled reference; a different successful reference means the buyer paid twice.
     *
     * @param array<string, mixed> $payment
     */
    private function flagSecondPayment(Order $order, array $payment): void
    {
        $settled = $order->metadata['payment_reference'] ?? null;
        $incoming = $payment['reference'] ?? null;

        if (
            is_string($settled)
            && is_string($incoming)
            && $incoming !== $settled
            && ($payment['status'] ?? null) === 'success'
        ) {
            Log::critical('A second successful payment arrived for a settled order and must be refunded.', [
                'order_id' => $order->getKey(),
                'order_reference' => $order->reference,
                'settled_reference' => $settled,
                'duplicate_reference' => $incoming,
                'payment_amount' => $payment['amount'] ?? null,
            ]);
        }
    }
}
