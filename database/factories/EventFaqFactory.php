<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventFaq;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFaqFactory extends Factory
{
    protected $model = EventFaq::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'question' => fake()->sentence() . '?',
            'answer' => fake()->paragraph(),
            'category' => 'General',
            'sort_order' => 1,
            'is_published' => true,
        ];
    }
}
