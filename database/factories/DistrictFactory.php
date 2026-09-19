<?php

namespace Database\Factories;

use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The name is unique in the schema, so it has to be unique here - a test
     * that makes two districts is making two real ones.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'is_active' => true,
        ];
    }

    /**
     * Take the district off the pickers, leaving its towns alone.
     */
    public function retired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
