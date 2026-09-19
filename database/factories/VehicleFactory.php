<?php

namespace Database\Factories;

use App\enum\CabinClass;
use App\enum\VehicleModel;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $model = fake()->randomElement(VehicleModel::cases());

        return [
            'user_id' => User::factory()->driver(),
            'registration_number' => 'DHAKA METRO-GA-'.fake()->unique()->numerify('##-####'),
            'model' => $model,
            'cabin_class' => fake()->randomElement(CabinClass::cases()),
            'seats' => $model->typicalSeats(),
        ];
    }

    /**
     * Indicate the vehicle is air conditioned.
     */
    public function airConditioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'cabin_class' => CabinClass::Ac,
        ]);
    }

    /**
     * Indicate which model the vehicle is, seats following from it.
     */
    public function ofModel(VehicleModel $model): static
    {
        return $this->state(fn (array $attributes) => [
            'model' => $model,
            'seats' => $model->typicalSeats(),
        ]);
    }
}
