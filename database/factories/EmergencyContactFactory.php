<?php

namespace Database\Factories;

use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyContact>
 */
class EmergencyContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'relation' => fake()->randomElement(['Father', 'Mother', 'Spouse', 'Sibling', 'Friend']),
            'dial_code' => '00880',
            'msisdn' => fake()->unique()->numerify('018########'),
            'is_primary' => false,
        ];
    }

    /**
     * Indicate that this is the contact called first.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
