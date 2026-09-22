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
            'email' => 'admin@yopmail.com',
            'msisdn' => '1700000001',
            'password' => $password,
        ]);

        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Passenger',
            'email' => 'passenger@yopmail.com',
            'msisdn' => '1700000002',
            'password' => $password,
        ]);

        User::factory()->driver()->create([
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'email' => 'driver@yopmail.com',
            'msisdn' => '1700000003',
            'password' => $password,
        ]);
    }
}
