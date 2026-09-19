<?php

namespace Database\Factories;

use App\enum\BookingStatus;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ride_id' => Ride::factory(),
            'user_id' => User::factory()->passenger(),
            'seats' => 1,

            /*
             * Where a request arrives, and what most tests want - a booking
             * the driver has not answered yet still holds its seats, so the
             * seat arithmetic reads the same either way.
             */
            'status' => BookingStatus::Pending,
        ];
    }

    /**
     * The driver has said yes.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Confirmed,
            'decided_at' => now(),
        ]);
    }

    /**
     * The driver has said no, and the seats went back on sale.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Declined,
            'decided_at' => now(),
        ]);
    }

    /**
     * A trip she has taken and scored.
     *
     * Confirmed, because only a confirmed booking can be rated - a state
     * the two always reach together, so the factory does too.
     */
    public function rated(int $rating): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Confirmed,
            'decided_at' => now(),
            'rating' => $rating,
            'rated_at' => now(),
        ]);
    }

    /**
     * Book the given number of seats.
     */
    public function ofSeats(int $seats): static
    {
        return $this->state(fn (array $attributes): array => [
            'seats' => $seats,
        ]);
    }
}
