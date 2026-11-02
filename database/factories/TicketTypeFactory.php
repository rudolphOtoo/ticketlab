<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        $capacity = fake()->numberBetween(50, 500);

        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Regular Pass', 'VIP Gold Pass', 'Student Pass']),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([50.00, 100.00, 150.00, 200.00]),
            'total_capacity' => $capacity,
            'available_quantity' => $capacity,
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addDays(14),
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn(): array => [
            'available_quantity' => 0,
        ]);
    }
}
