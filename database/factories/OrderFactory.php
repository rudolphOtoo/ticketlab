<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $net = 100.00;

        return [
            'organization_id' => Organization::factory(),
            'subdivision_id' => Subdivision::factory(),
            'reservation_id' => null,
            'user_id' => null,
            'reference' => 'TKT-' . strtoupper(Str::random(12)),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'gross_amount' => $net,
            'net_amount' => $net,
            'fee_amount' => 0.00,
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::MobileMoney,
            'payment_status' => OrderStatus::Pending,
            'paid_at' => null,
            'metadata' => [],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn(): array => [
            'payment_status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
