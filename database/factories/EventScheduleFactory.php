<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventScheduleFactory extends Factory
{
    protected $model = EventSchedule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'day_label' => 'Day 1',
            'session_title' => fake()->sentence(3),
            'speaker_name' => fake()->name(),
            'venue_hall' => 'Main Hall',
            'description' => fake()->sentence(),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'sort_order' => 1,
        ];
    }
}
