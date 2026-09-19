<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Stop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stop>
 */
class StopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The name is unique in the schema, so it is unique here too - a test
     * building two corridors must not trip over a repeated town.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'district_id' => District::factory(),
            'is_active' => true,
        ];
    }

    /**
     * A stop that is no longer offered in the pickers.
     */
    public function retired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
