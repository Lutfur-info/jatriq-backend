<?php

namespace Database\Factories;

use App\Models\Stop;
use App\Models\TravelRoute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TravelRoute>
 */
class TravelRouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Dhaka - '.fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }

    /**
     * Lay the given stops along the corridor, in the order they are passed.
     *
     * Outbound from Dhaka, like every seeded route, so the first argument
     * holds the lowest sequence. Spaced in tens for the same reason the
     * seeder spaces them: a stop can be inserted later without renumbering.
     */
    public function along(Stop ...$stops): static
    {
        return $this->afterCreating(function (TravelRoute $route) use ($stops): void {
            // array_values because a variadic collects named arguments under
            // string keys, which are no use as a position.
            foreach (array_values($stops) as $index => $stop) {
                $route->stops()->attach($stop, ['sequence' => ($index + 1) * 10]);
            }
        });
    }

    /**
     * A corridor that is no longer offered.
     */
    public function retired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
