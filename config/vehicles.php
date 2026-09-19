<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seat Count
    |--------------------------------------------------------------------------
    |
    | The range a driver may claim, counting PASSENGER seats only - the
    | driver's own seat is never included, because what a rider needs to know
    | is how many people can travel. One is a single passenger; the ceiling is
    | generous so a coach is accepted without a code change.
    | VehicleModel::typicalSeats() is only a starting point offered to the
    | driver - a refitted microbus is common, so the model never dictates the
    | count.
    |
    */

    'seats' => [
        'min' => (int) env('VEHICLE_SEATS_MIN', 1),
        'max' => (int) env('VEHICLE_SEATS_MAX', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration Number
    |--------------------------------------------------------------------------
    |
    | A Bangladeshi plate reads like "DHAKA METRO-GA-11-2233", so the column
    | is wide enough for the city, the series and both number groups.
    |
    */

    'registration_number' => [
        'max' => (int) env('VEHICLE_REGISTRATION_MAX', 40),
    ],

];
