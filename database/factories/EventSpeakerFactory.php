<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventSpeakerFactory extends Factory
{
    protected $model = EventSpeaker::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'title_role' => fake()->jobTitle(),
            'topic' => fake()->sentence(3),
            'bio' => fake()->paragraph(),
            'photo_path' => null,
            'sort_order' => 1,
            'is_featured' => false,
            'social_links' => [],
        ];
    }
}
