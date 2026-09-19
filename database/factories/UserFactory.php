<?php

namespace Database\Factories;

use App\enum\Gender;
use App\enum\Role;
use App\enum\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'dial_code' => '00880',
            'msisdn' => fake()->unique()->numerify('017########'),
            'msisdn_verified_at' => now(),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->date(max: '-18 years'),
            'role' => Role::Passenger,
            'is_active' => true,
            'verification_status' => VerificationStatus::Unverified,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Admin,
        ]);
    }

    /**
     * Indicate that the user is a driver.
     */
    public function driver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Driver,
        ]);
    }

    /**
     * Indicate that the user is a passenger, which is also the default.
     */
    public function passenger(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Passenger,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has not confirmed their msisdn yet.
     *
     * This is the state a freshly registered account is left in.
     */
    public function unverifiedMsisdn(): static
    {
        return $this->state(fn (array $attributes) => [
            'msisdn_verified_at' => null,
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user's identity documents have been accepted.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the user's documents are waiting on a reviewer.
     */
    public function awaitingVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::Pending,
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the account has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
