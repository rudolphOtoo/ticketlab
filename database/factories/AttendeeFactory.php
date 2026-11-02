<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attendee;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attendee>
 */
class AttendeeFactory extends Factory
{
    protected $model = Attendee::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'organization_id' => Organization::factory(),
            'subdivision_id' => Subdivision::factory(),
            'ticket_type_id' => TicketType::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'qr_code_hash' => hash('sha256', Str::uuid()->toString() . microtime()),
            'checked_in_at' => null,
            'checked_in_by' => null,
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn(): array => [
            'checked_in_at' => now(),
        ]);
    }
}
