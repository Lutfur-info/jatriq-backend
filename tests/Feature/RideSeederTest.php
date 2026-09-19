<?php

use App\enum\VerificationStatus;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RideSeeder;

/*
 * RideSeeder prints a table of searches and what each should return, and a
 * seeder that advertises the wrong answers is worse than one that says
 * nothing - it sends somebody hunting for a bug that is not there. These pin
 * the rows it claims.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

/**
 * The origins a passenger travelling `$from` to `$to` is offered.
 *
 * @return array<int, string>
 */
function seededOrigins(string $from, string $to): array
{
    $rides = test()->getJson(route('api.rides.index', [
        'from_stop_id' => Stop::query()->where('name', $from)->value('id'),
        'to_stop_id' => Stop::query()->where('name', $to)->value('id'),
    ]))->assertOk()->json('data.rides');

    return array_map(fn (array $ride): string => $ride['origin']['label'], $rides);
}

it('leaves the driver able to publish and the passenger able to book', function () {
    $driver = User::query()->where('msisdn', '1700000002')->sole();
    $passenger = User::query()->where('msisdn', '1700000001')->sole();

    // Without these two the ride screens are unreachable by hand: publishing
    // needs the badge and a vehicle, booking needs the badge.
    expect($driver->verification_status)->toBe(VerificationStatus::Verified)
        ->and($driver->vehicle)->not->toBeNull()
        ->and($driver->vehicle->seats)->toBe(11)
        ->and($passenger->verification_status)->toBe(VerificationStatus::Verified);
});

it('seeds eight rides, six of them bookable', function () {
    expect(Ride::query()->count())->toBe(8);

    // One full, one departed - both upcoming-and-on-the-road in every other
    // respect, which is what makes them worth seeding.
    expect(test()->getJson(route('api.rides.index'))->assertOk()->json('data.rides'))
        ->toHaveCount(6);
});

it('answers every search the seeder advertises', function () {
    expect(seededOrigins('Laksam', 'Dhaka'))
        ->toBe(['Sonaimuri', 'Bipulashar', 'Maijdee']);

    /*
     * The row people misread. Bipulashar is absent because it starts PAST
     * Khila and never goes there; Sonaimuri and Maijdee both start beyond
     * Khila and do.
     */
    expect(seededOrigins('Khila', 'Laksam'))->toBe(['Sonaimuri', 'Maijdee']);

    // One town closer in, and Bipulashar joins them.
    expect(seededOrigins('Natherpetua', 'Laksam'))
        ->toBe(['Sonaimuri', 'Bipulashar', 'Maijdee']);

    // The shared road out of Dhaka, so a Chattogram ride answers too.
    expect(seededOrigins('Cumilla', 'Dhaka'))
        ->toBe(['Sonaimuri', 'Bipulashar', 'Chattogram', 'Maijdee']);

    expect(seededOrigins('Dhaka', 'Laksam'))->toBe(['Dhaka'])
        ->and(seededOrigins('Maijdee', 'Dhaka'))->toBe(['Maijdee'])
        // The Chowmuhani ride has left, so only the one from further out.
        ->and(seededOrigins('Chowmuhani', 'Dhaka'))->toBe(['Maijdee'])
        // Different roads entirely.
        ->and(seededOrigins('Laksam', 'Sylhet'))->toBe([]);
});

it('skips rather than doubling the board when re-run', function () {
    $this->seed(RideSeeder::class);

    expect(Ride::query()->count())->toBe(8);
});
