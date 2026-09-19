<?php

namespace Database\Factories;

use App\Models\Ride;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Ride>
 */
class RideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The vehicle is resolved from whichever driver the ride ends up on, so a
     * generated ride is never in a car belonging to somebody else.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'vehicle_id' => fn (array $attributes): int => Vehicle::factory()
                ->create(['user_id' => $attributes['user_id']])
                ->id,

            // Dhaka to Chattogram, the country's busiest intercity run.
            'origin_label' => 'Gulshan 1, Dhaka',
            'destination_label' => 'GEC Circle, Chattogram',

            'departs_at' => Carbon::now()->addDay(),
            'seat_price' => fake()->numberBetween(300, 1500),
            'seats_offered' => 4,
        ];
    }

    /**
     * Put the ride on a driver who already has a vehicle.
     */
    public function forDriver(User $driver): static
    {
        return $this->state(function (array $attributes) use ($driver): array {
            $vehicle = $driver->vehicle
                ?: Vehicle::factory()->create(['user_id' => $driver->id]);

            return [
                'user_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ];
        });
    }

    /**
     * Schedule the departure.
     */
    public function departingAt(Carbon $departsAt): static
    {
        return $this->state(fn (array $attributes): array => [
            'departs_at' => $departsAt,
        ]);
    }

    /**
     * Run the ride between two stops on a corridor.
     *
     * Sets everything RideService sets when a driver publishes: the corridor,
     * both stops, both sequences read from the pivot, and the snapshot of
     * each stop's name. Pass the stops in travel order - the
     * origin first - so a ride toward Dhaka gets the descending sequences
     * that mark it as inbound.
     *
     * The default state deliberately leaves all of this null, which is the
     * shape of a ride published before corridors existed.
     */
    public function between(TravelRoute $route, Stop $origin, Stop $destination): static
    {
        return $this->state(function (array $attributes) use ($route, $origin, $destination): array {
            $sequences = $route->stops()
                ->whereIn('stops.id', [$origin->id, $destination->id])
                ->pluck('travel_route_stop.sequence', 'stops.id');

            return [
                'travel_route_id' => $route->id,

                'origin_stop_id' => $origin->id,
                'origin_sequence' => $sequences[$origin->id] ?? null,
                'origin_label' => $origin->name,

                'destination_stop_id' => $destination->id,
                'destination_sequence' => $sequences[$destination->id] ?? null,
                'destination_label' => $destination->name,
            ];
        });
    }
}
