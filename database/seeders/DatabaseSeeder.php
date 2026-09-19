<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The password every seeded account signs in with.
     */
    private const PASSWORD = 'Win4Win$';

    /**
     * Seed the application's database.
     *
     * Admins cannot be created through the registration API, so the first
     * one is seeded here.
     */
    public function run(): void
    {
        /*
         * Reference data, not test data: a driver cannot publish a ride and a
         * passenger cannot search for one until the corridors and their stops
         * exist, so this runs on every environment.
         *
         * Districts come first, because a stop is filed under one by name.
         */
        $this->call(DistrictSeeder::class);
        $this->call(TravelRouteSeeder::class);

        $password = Hash::make(self::PASSWORD);

        User::factory()->admin()->create([
            'first_name' => 'Jatriq',
            'last_name' => 'Admin',
            'email' => 'admin@jatriq.test',
            'msisdn' => '1700000000',
            'password' => $password,
        ]);

        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'email' => 'passenger@jatriq.test',
            'msisdn' => '1700000001',
            'password' => $password,
        ]);

        User::factory()->driver()->create([
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'email' => 'driver@jatriq.test',
            'msisdn' => '1700000002',
            'password' => $password,
        ]);

        /*
         * Traffic on the roads above, and the two things that otherwise make
         * the ride feature untestable by hand: the driver gets a vehicle and
         * a badge so they can publish, and the passenger gets a badge so she
         * can book. Development data - it runs after the accounts because it
         * needs them, and it prints the searches worth trying.
         */
        $this->call(RideSeeder::class);
    }
}
