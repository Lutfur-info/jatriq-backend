<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seat Price
    |--------------------------------------------------------------------------
    |
    | What a driver may charge per passenger seat, in BDT. There is no
    | currency column: the platform serves Bangladesh only, so every fare on
    | every ride is taka. The ceiling is there to catch a fare typed with one
    | zero too many, not to cap a legitimate intercity price.
    |
    */

    'price' => [
        'min' => (int) env('RIDE_SEAT_PRICE_MIN', 1),
        'max' => (int) env('RIDE_SEAT_PRICE_MAX', 100000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Board
    |--------------------------------------------------------------------------
    |
    | How many rides the public web board puts on a page. It is only the web
    | board that pages: GET /api/rides still answers with every bookable ride
    | in one response, because that shape is what the mobile clients already
    | parse.
    |
    */

    'board' => [
        'per_page' => (int) env('RIDE_BOARD_PER_PAGE', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Departure Window
    |--------------------------------------------------------------------------
    |
    | How far ahead a ride may be scheduled. The lead time stops a ride being
    | posted after it has effectively left, so a passenger has some chance of
    | finding and booking it; the horizon keeps the upcoming list meaningful.
    |
    */

    'departure' => [
        'min_lead_minutes' => (int) env('RIDE_MIN_LEAD_MINUTES', 15),
        'max_days_ahead' => (int) env('RIDE_MAX_DAYS_AHEAD', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locations
    |--------------------------------------------------------------------------
    |
    | Both ends of a ride are **stops on a corridor**, picked from the list
    | GET /api/routes serves - not free text and not an arbitrary coordinate.
    | That is what makes a ride searchable: a passenger boarding partway along
    | can only be matched against a known position on a known road.
    |
    | The ride still stores a label and a latitude/longitude for each end, but
    | as a snapshot copied from the stop when the ride is published, so
    | renaming a stop never rewrites a trip somebody already agreed to. They
    | are decimal columns rather than a spatial POINT: the suite runs on
    | sqlite, which has no spatial type, and nothing asks the database a
    | distance question - matching is on sequence, never on proximity.
    |
    | There are no tunables left here. The bounds that used to live in this
    | block were for text a driver typed; a stop id is checked against the
    | stops table instead.
    |
    */

];
