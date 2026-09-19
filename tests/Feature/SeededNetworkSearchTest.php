<?php

use App\Models\Booking;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Models\User;
use Database\Seeders\TravelRouteSeeder;
use Illuminate\Support\Carbon;

/*
 * The search against the corridors the product actually ships, rather than
 * against a fixture. These exist because the answer to "will this ride serve
 * that passenger" is not obvious from the two town names - it depends on
 * which side of the boarding point the vehicle set out from, and the seeded
 * order is the only thing that says.
 *
 * The Noakhali branch, outbound from Dhaka:
 *
 *   Dhaka 10 · Kanchpur 20 · Daudkandi 30 · Chandina 40 · Cumilla 50
 *   Laksam 60 · Natherpetua 62 · Bipulashar 65 · Khila 68
 *   Sonaimuri 70 · Chowmuhani 80 · Maijdee 90
 */
beforeEach(function () {
    $this->seed(TravelRouteSeeder::class);

    $this->noakhali = TravelRoute::query()->where('slug', 'dhaka-noakhali')->sole();
});

/**
 * A bookable ride between two seeded towns on the Noakhali branch.
 */
function seededRide(string $from, string $to, int $seats = 4): Ride
{
    return Ride::factory()
        ->between(
            test()->noakhali,
            Stop::query()->where('name', $from)->sole(),
            Stop::query()->where('name', $to)->sole(),
        )
        ->departingAt(Carbon::now()->addHours(3))
        ->create(['seats_offered' => $seats]);
}

/**
 * The origins a passenger travelling `$from` to `$to` is offered.
 *
 * @return array<int, string>
 */
function originsBetween(string $from, string $to): array
{
    $response = test()->getJson(route('api.rides.index', [
        'from_stop_id' => Stop::query()->where('name', $from)->value('id'),
        'to_stop_id' => Stop::query()->where('name', $to)->value('id'),
    ]))->assertOk();

    return array_column($response->json('data.rides'), 'origin')
        ? array_column(array_column($response->json('data.rides'), 'origin'), 'label')
        : [];
}

it('inserts the three new towns between Laksam and Sonaimuri', function () {
    $sequences = $this->noakhali->stops->pluck('pivot.sequence', 'name');

    expect($sequences['Laksam'])->toBe(60)
        ->and($sequences['Natherpetua'])->toBe(62)
        ->and($sequences['Bipulashar'])->toBe(65)
        ->and($sequences['Khila'])->toBe(68)
        // The point of the gap: adding three towns did not move Sonaimuri,
        // so the rides already published against it still sit where they did.
        ->and($sequences['Sonaimuri'])->toBe(70)
        ->and($sequences['Chowmuhani'])->toBe(80)
        ->and($sequences['Maijdee'])->toBe(90);
});

it('does not offer a Bipulashar ride to somebody boarding at Khila', function () {
    /*
     * The question this file was written for. Khila is FURTHER from Dhaka
     * than Bipulashar, so a vehicle that sets out from Bipulashar heading to
     * Dhaka has already left Khila behind - it never goes there at all. The
     * refusal is the rule working, not a gap in it.
     */
    seededRide('Bipulashar', 'Dhaka');

    expect(originsBetween('Khila', 'Laksam'))->toBe([])
        ->and(originsBetween('Khila', 'Dhaka'))->toBe([]);
});

it('offers that same ride to everybody it really does pass', function () {
    seededRide('Bipulashar', 'Dhaka');

    // Natherpetua is between Bipulashar and Laksam, so the vehicle passes it.
    expect(originsBetween('Natherpetua', 'Laksam'))->toBe(['Bipulashar'])
        ->and(originsBetween('Natherpetua', 'Dhaka'))->toBe(['Bipulashar'])
        // Boarding where it starts is the ordinary case.
        ->and(originsBetween('Bipulashar', 'Cumilla'))->toBe(['Bipulashar'])
        // And the whole shared stretch back into Dhaka.
        ->and(originsBetween('Cumilla', 'Dhaka'))->toBe(['Bipulashar']);
});

it('serves a Khila passenger from a ride that really does start further out', function () {
    // What that passenger actually needs: a vehicle setting out beyond Khila.
    seededRide('Sonaimuri', 'Dhaka');
    seededRide('Khila', 'Dhaka');

    expect(originsBetween('Khila', 'Laksam'))->toBe(['Sonaimuri', 'Khila'])
        // Boarding further out than either still finds neither.
        ->and(originsBetween('Maijdee', 'Laksam'))->toBe([]);
});

it('keeps the new towns out of a search running the other way', function () {
    // Outbound from Dhaka, so it passes Natherpetua then Bipulashar then Khila.
    seededRide('Dhaka', 'Maijdee');

    expect(originsBetween('Laksam', 'Khila'))->toBe(['Dhaka'])
        ->and(originsBetween('Khila', 'Natherpetua'))->toBe([])
        ->and(originsBetween('Bipulashar', 'Maijdee'))->toBe(['Dhaka']);
});

it('drops a new town from the board once the ride fills up', function () {
    $ride = seededRide('Bipulashar', 'Dhaka', seats: 2);

    Booking::factory()->for($ride)->ofSeats(2)->create([
        'user_id' => User::factory()->passenger(),
    ]);

    expect(originsBetween('Natherpetua', 'Dhaka'))->toBe([]);
});
