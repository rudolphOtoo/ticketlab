<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventFormField;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventFormField>
 */
class EventFormFieldFactory extends Factory
{
    protected $model = EventFormField::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'field_key' => fake()->unique()->slug(2),
            'label' => fake()->words(3, true),
            'input_type' => 'text',
            'placeholder' => null,
            'options' => null,
            'validation_rules' => null,
            'is_required' => false,
            'sort_order' => 1,
            'is_active' => true,
        ];
    }
}
