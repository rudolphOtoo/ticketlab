<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentReviewService
{
    public function __construct(
        private readonly PaymentGatewayMetadata $gatewayMetadata,
    ) {}

    /**
     * @param array<string, mixed> $payment
     */
    public function mark(Order $order, string $reason, array $payment): void
    {
        try {
            DB::transaction(function () use ($order, $reason, $payment): void {
                /** @var Order|null $lockedOrder */
                $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

                if (
                    $lockedOrder === null
                    || in_array($lockedOrder->payment_status, [OrderStatus::Paid, OrderStatus::RequiresReview], true)
                ) {
                    return;
                }

                $lockedOrder->forceFill([
                    'payment_status' => OrderStatus::RequiresReview,
                    'metadata' => array_merge(
                        $lockedOrder->metadata ?? [],
                        $this->gatewayMetadata->forPayment($payment),
                        [
                            'requires_manual_review' => true,
                            'payment_review_reason' => $reason,
                            'payment_review_flagged_at' => now()->format(DATE_ATOM),
                        ],
                    ),
                ])->save();
            });
        } catch (Throwable $exception) {
            Log::critical('Unable to flag an order for manual payment review.', [
                'order_id' => $order->getKey(),
                'order_reference' => $order->reference,
                'reason' => $reason,
                'review_flag_error' => $exception->getMessage(),
            ]);
        }
    }
}
