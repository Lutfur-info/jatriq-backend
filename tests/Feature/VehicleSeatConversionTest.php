<?php

use App\Models\User;
use App\Models\Vehicle;

/**
 * The migration that turned `vehicles.seats` from a seat total into a
 * passenger count. Rows written under the old meaning read one too high, so
 * `up()` decrements them - and must not drive a converted row below the
 * floor if it is ever replayed.
 */
function seatConversion(): object
{
    return require base_path(
        'database/migrations/2026_09_07_160739_convert_vehicle_seats_to_passenger_seats.php'
    );
}

it('drops a stored seat total to its passenger count', function () {
    $driver = User::factory()->driver()->create();
    Vehicle::factory()->for($driver)->create(['seats' => 12]);

    seatConversion()->up();

    expect($driver->vehicle->fresh()->seats)->toBe(11);
});

it('leaves a row already at the floor alone', function () {
    $driver = User::factory()->driver()->create();
    Vehicle::factory()->for($driver)->create(['seats' => 1]);

    seatConversion()->up();

    expect($driver->vehicle->fresh()->seats)->toBe(1);
});

it('puts the seat total back when rolled back', function () {
    $driver = User::factory()->driver()->create();
    Vehicle::factory()->for($driver)->create(['seats' => 12]);

    $migration = seatConversion();
    $migration->up();
    $migration->down();

    expect($driver->vehicle->fresh()->seats)->toBe(12);
});
