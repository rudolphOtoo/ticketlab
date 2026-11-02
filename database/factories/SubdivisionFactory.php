<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Subdivision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subdivision>
 */
class SubdivisionFactory extends Factory
{
    protected $model = Subdivision::class;

    public function definition(): array
    {
        $name = 'Zone ' . fake()->unique()->city();

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'paystack_subaccount_code' => 'ACCT_' . strtoupper(Str::random(10)),
        ];
    }
}
