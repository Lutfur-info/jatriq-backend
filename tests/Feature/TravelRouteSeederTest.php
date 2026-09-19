<?php

use App\Models\Stop;
use App\Models\TravelRoute;
use Database\Seeders\TravelRouteSeeder;

/*
 * The corridors are reference data, not fixtures: a driver cannot publish and
 * a passenger cannot search until they exist, and their ORDER is the product.
 * These pin the two facts the search rests on - that the towns run the right
 * way round, and that a town on several roads is one shared row.
 */
beforeEach(function () {
    $this->seed(TravelRouteSeeder::class);
});

it('runs the Noakhali branch outbound from Dhaka', function () {
    $route = TravelRoute::query()->where('slug', 'dhaka-noakhali')->sole();

    expect($route->stops->pluck('name')->all())->toBe([
        'Dhaka', 'Kanchpur', 'Daudkandi', 'Chandina', 'Cumilla',
        'Laksam', 'Natherpetua', 'Bipulashar', 'Khila',
        'Sonaimuri', 'Chowmuhani', 'Maijdee',
    ]);

    // Sonaimuri is further out than Laksam, which is what makes a vehicle
    // leaving Sonaimuri for Dhaka pass through Laksam on the way.
    $sequences = $route->stops->pluck('pivot.sequence', 'name');

    expect($sequences['Sonaimuri'])->toBeGreaterThan($sequences['Laksam'])
        ->and($sequences['Laksam'])->toBeGreaterThan($sequences['Cumilla'])
        ->and($sequences['Cumilla'])->toBeGreaterThan($sequences['Dhaka']);
});

it('shares one Cumilla between every corridor that passes through it', function () {
    $cumilla = Stop::query()->where('name', 'Cumilla')->sole();

    expect($cumilla->travelRoutes->pluck('slug')->sort()->values()->all())->toBe([
        'dhaka-chattogram', 'dhaka-coxs-bazar', 'dhaka-noakhali',
    ]);
});

it('spaces the sequences so a town can be inserted without a renumber', function () {
    $route = TravelRoute::query()->where('slug', 'dhaka-barishal')->sole();

    expect($route->stops->pluck('pivot.sequence')->all())->toBe([10, 20, 30, 40, 50, 60]);
});

it('takes an inserted town out of the gap, leaving the rest where they were', function () {
    /*
     * Natherpetua, Bipulashar and Khila were added between Laksam and
     * Sonaimuri after rides were already being published on this road. They
     * took 62, 65 and 68 out of the gap, so Sonaimuri and everything past it
     * kept its number - a ride copies its sequences when it is published and
     * nothing goes back to correct them, so a shift here would silently
     * mis-position every ride already out there.
     */
    $sequences = TravelRoute::query()
        ->where('slug', 'dhaka-noakhali')
        ->sole()
        ->stops
        ->pluck('pivot.sequence', 'name');

    expect($sequences['Laksam'])->toBe(60)
        ->and($sequences['Natherpetua'])->toBe(62)
        ->and($sequences['Bipulashar'])->toBe(65)
        ->and($sequences['Khila'])->toBe(68)
        ->and($sequences['Sonaimuri'])->toBe(70)
        ->and($sequences['Chowmuhani'])->toBe(80)
        ->and($sequences['Maijdee'])->toBe(90);
});

it('re-runs without duplicating a corridor or a town', function () {
    $routes = TravelRoute::query()->count();
    $stops = Stop::query()->count();

    $this->seed(TravelRouteSeeder::class);

    expect(TravelRoute::query()->count())->toBe($routes)
        ->and(Stop::query()->count())->toBe($stops);
});
