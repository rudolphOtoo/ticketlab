<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates or refreshes the single pending order attached to a checkout reservation,
 * so retrying checkout reuses the same order reference instead of minting duplicates.
 */
class PendingOrderService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function persist(Reservation $reservation, array $attributes): Order
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(fn(): Order => $this->storeOrder($reservation, $attributes));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new InvalidArgumentException('Unable to create a checkout order.');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function storeOrder(Reservation $reservation, array $attributes): Order
    {
        $order = Order::query()->where('reservation_id', $reservation->getKey())->lockForUpdate()->first();

        if ($order !== null) {
            if ($order->payment_status !== OrderStatus::Pending) {
                throw new InvalidArgumentException(
                    'This checkout already has a payment that is under review or completed.',
                );
            }

            $order->forceFill($attributes)->save();

            return $order->refresh();
        }

        return Order::query()->create(
            $attributes
            + [
                'user_id' => null,
                'reference' => $this->generateReference(),
                'payment_method' => PaymentMethod::MobileMoney,
                'payment_status' => OrderStatus::Pending,
                'paid_at' => null,
            ],
        );
    }

    private function generateReference(): string
    {
        do {
            $reference = 'TKT-' . strtoupper((string) Str::ulid());
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
