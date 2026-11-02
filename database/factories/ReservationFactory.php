<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Subdivision;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'ticket_type_id' => TicketType::factory(),
            'subdivision_id' => Subdivision::factory(),
            'session_id' => Str::uuid()->toString(),
            'quantity' => 1,
            'expires_at' => now()->addMinutes(15),
            'status' => ReservationStatus::Active,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn(): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
