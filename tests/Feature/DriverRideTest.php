<?php

use App\enum\VehicleModel;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Repositories\Contracts\RideRepository;
use App\Repositories\Eloquent\RideEloquentRepository;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    // Publishing and editing need the identity badge, so the driver under
    // test carries it; the refusals without it are their own tests below.
    $this->driver = User::factory()->driver()->verified()->create();

    // A HiAce carries 11 passengers, so that is the seat ceiling under test.
    $this->vehicle = Vehicle::factory()
        ->for($this->driver)
        ->ofModel(VehicleModel::HiAce)
        ->create();

    /*
     * A ride runs between two stops on a corridor, so there has to be one.
     * The Noakhali branch, ordered outbound from Dhaka the way every seeded
     * route is - so a trip toward Dhaka runs down the sequences.
     */
    $this->dhaka = Stop::factory()->create(['name' => 'Dhaka']);
    $this->cumilla = Stop::factory()->create(['name' => 'Cumilla']);
    $this->laksam = Stop::factory()->create(['name' => 'Laksam']);
    $this->sonaimuri = Stop::factory()->create(['name' => 'Sonaimuri']);

    $this->route = TravelRoute::factory()
        ->along($this->dhaka, $this->cumilla, $this->laksam, $this->sonaimuri)
        ->create(['name' => 'Dhaka - Noakhali']);
});

/**
 * A complete ride payload, overridable field by field.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ridePayload(array $overrides = []): array
{
    return [
        // Sonaimuri toward Dhaka - the trip the whole search is built round.
        'origin_stop_id' => test()->sonaimuri->id,
        'destination_stop_id' => test()->dhaka->id,

        'departs_at' => Carbon::now()->addHours(3)->toIso8601String(),
        'seat_price' => '650.00',
        'seats_offered' => 4,
        ...$overrides,
    ];
}

it('publishes a ride with both points, the departure and the seat price', function () {
    $departsAt = Carbon::now()->addHours(3);

    $this->actingAs($this->driver)
        ->postJson(route('api.driver.rides.store'), ridePayload([
            'departs_at' => $departsAt->toIso8601String(),
        ]))
        ->assertStatus(Response::HTTP_CREATED)
        // The label is copied off the stop the driver picked, not sent by
        // them.
        ->assertJsonPath('data.ride.origin.stop_id', $this->sonaimuri->id)
        ->assertJsonPath('data.ride.origin.label', 'Sonaimuri')
        ->assertJsonPath('data.ride.destination.stop_id', $this->dhaka->id)
        ->assertJsonPath('data.ride.destination.label', 'Dhaka')
        // The corridor the two stops share, resolved rather than claimed.
        ->assertJsonPath('data.ride.route.id', $this->route->id)
        ->assertJsonPath('data.ride.route.name', 'Dhaka - Noakhali')
        ->assertJsonPath('data.ride.seat_price', '650.00')
        ->assertJsonPath('data.ride.seats_offered', 4)
        // The car the seats are in rides along, so a client needs one call.
        ->assertJsonPath('data.ride.vehicle.registration_number', $this->vehicle->registration_number)
        ->assertJsonPath('data.ride.vehicle.seats', 11);

    $ride = Ride::query()->sole();

    expect($ride->user_id)->toBe($this->driver->id)
        ->and($ride->vehicle_id)->toBe($this->vehicle->id)
        ->and($ride->travel_route_id)->toBe($this->route->id)
        // Sonaimuri is fourth out of Dhaka, Dhaka is first: a trip running
        // down the sequences, which is what marks it as Dhaka-bound.
        ->and($ride->origin_sequence)->toBe(40)
        ->and($ride->destination_sequence)->toBe(10)
        ->and($ride->seats_offered)->toBe(4)
        ->and($ride->seat_price)->toBe('650.00')
        ->and($ride->departs_at->toDateTimeString())->toBe($departsAt->toDateTimeString());
});

it('requires every detail', function () {
    $this->actingAs($this->driver)
        ->postJson(route('api.driver.rides.store'), [])
        ->assertJsonValidationErrors([
            'origin_stop_id',
            'destination_stop_id',
            'departs_at',
            'seat_price',
            'seats_offered',
        ]);

    expect(Ride::query()->count())->toBe(0);
});

it('takes the label from the stop, and carries no place id at all', function () {
    // The label is the stop's, not the driver's: a client sends two stop ids
    // and nothing else about the road. The Google place id that used to be
    // snapshotted beside it went on 2026-09-19, off the stop and the ride
    // alike, so both ends are a label and a position now.
    $this->actingAs($this->driver)
        ->postJson(route('api.driver.rides.store'), ridePayload())
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.ride.origin.label', $this->sonaimuri->name)
        ->assertJsonPath('data.ride.destination.label', $this->dhaka->name)
        ->assertJsonMissingPath('data.ride.origin.place_id')
        ->assertJsonMissingPath('data.ride.destination.place_id');
});

it('refuses a stop that is not on the network', function () {
    $this->actingAs($this->driver)
        ->postJson(route('api.driver.rides.store'), ridePayload([
            'origin_stop_id' => 9999,
            'destination_stop_id' => 9998,
        ]))
        ->assertJsonValidationErrors(['origin_stop_id', 'destination_stop_id']);

    expect(Ride::query()->count())->toBe(0);
});

it('refuses a stop that has been retired', function () {
    // Retiring a stop takes it out of the pickers without disturbing the
    // rides already published against it.
    $this->sonaimuri->update(['is_active' => false]);

    $this->actingAs($this->driver)
        ->postJson(route('api.driver.rides.store'), ridePayload())
        ->assertJsonValidationErrorFor('origin_stop_id');

    expect(Ride::query()->count())->toBe(0);
});

it('refuses a destination that is the start point', function () {
    $this->actingAs($this->driver)
        ->postJson(route('api.driver.rides.store'), ridePayload([
            'destination_stop_id' => test()->sonaimuri->id,
        ]))
        ->assertJsonValidationErrorFor('destination_stop_id');

    expect(Ride::query()->count())->toBe(0);
});

it('refuses two stops that share no route', function () {
    // A town on a different road entirely - there is no through service, so
    // there is no trip to publish.
    $sylhet = Stop::factory()->create(['name' => 'Sylhet']);

    $this->actingAs($this->driver)
        ->postJson(route('api.driver.rides.store'), ridePayload([
            'destination_stop_id' => $sylhet->id,
        ]))
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('destination_stop_id');

    expect(Ride::query()->count())->toBe(0);
});

it('puts a trip on the corridor its two stops are closest together on', function () {
    /*
     * Cumilla and Dhaka are shared by the Noakhali branch and the Chattogram
     * highway, so something has to choose. The choice is cosmetic - it
     * decides the name the ride is shown under, never which passengers match
     * it - but it has to be the same every time.
     */
    $feni = Stop::factory()->create(['name' => 'Feni']);

    TravelRoute::factory()
        ->along($this->dhaka, $this->cumilla, $feni)
        ->create(['name' => 'Dhaka - Chattogram']);

    $this->actingAs($this->driver)
        ->postJson(route('api.driver.rides.store'), ridePayload([
            'origin_stop_id' => test()->cumilla->id,
        ]))
        ->assertStatus(Response::HTTP_CREATED);

    // Cumilla is second on both, so the tie falls to the lower route id.
    expect(Ride::query()->sole()->travel_route_id)->toBe($this->route->id);
});

it('holds the seats offered inside the vehicles passenger seats', function () {
    $this->actingAs($this->driver);

    // The HiAce carries 11, so 12 is a seat the vehicle does not have.
    $this->postJson(route('api.driver.rides.store'), ridePayload(['seats_offered' => 12]))
        ->assertJsonValidationErrorFor('seats_offered');

    $this->postJson(route('api.driver.rides.store'), ridePayload(['seats_offered' => 0]))
        ->assertJsonValidationErrorFor('seats_offered');

    // Selling every passenger seat, and selling just one, are both fine.
    $this->postJson(route('api.driver.rides.store'), ridePayload(['seats_offered' => 11]))
        ->assertStatus(Response::HTTP_CREATED);

    $this->postJson(route('api.driver.rides.store'), ridePayload(['seats_offered' => 1]))
        ->assertStatus(Response::HTTP_CREATED);
});

it('holds the departure inside the configured window', function () {
    $this->actingAs($this->driver);

    $lead = (int) config('rides.departure.min_lead_minutes');
    $days = (int) config('rides.departure.max_days_ahead');

    // Already gone.
    $this->postJson(route('api.driver.rides.store'), ridePayload([
        'departs_at' => Carbon::now()->subHour()->toIso8601String(),
    ]))->assertJsonValidationErrorFor('departs_at');

    // Inside the lead time, so no passenger could book it.
    $this->postJson(route('api.driver.rides.store'), ridePayload([
        'departs_at' => Carbon::now()->addMinutes($lead - 5)->toIso8601String(),
    ]))->assertJsonValidationErrorFor('departs_at');

    // Beyond the horizon.
    $this->postJson(route('api.driver.rides.store'), ridePayload([
        'departs_at' => Carbon::now()->addDays($days + 1)->toIso8601String(),
    ]))->assertJsonValidationErrorFor('departs_at');

    expect(Ride::query()->count())->toBe(0);
});

it('holds the seat price inside the configured range', function () {
    $this->actingAs($this->driver);

    $this->postJson(route('api.driver.rides.store'), ridePayload([
        'seat_price' => ((int) config('rides.price.min')) - 1,
    ]))->assertJsonValidationErrorFor('seat_price');

    $this->postJson(route('api.driver.rides.store'), ridePayload([
        'seat_price' => ((int) config('rides.price.max')) + 1,
    ]))->assertJsonValidationErrorFor('seat_price');

    $this->postJson(route('api.driver.rides.store'), ridePayload([
        'seat_price' => 'five hundred',
    ]))->assertJsonValidationErrorFor('seat_price');
});

it('refuses a driver who has not registered a vehicle', function () {
    // No vehicle means no seats to sell and no car to describe.
    $fresh = User::factory()->driver()->verified()->create();

    $this->actingAs($fresh)
        ->postJson(route('api.driver.rides.store'), ridePayload())
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('vehicle');

    expect(Ride::query()->count())->toBe(0);
});

it('never takes the driver or the vehicle from the request body', function () {
    $other = User::factory()->driver()->create();
    $otherVehicle = Vehicle::factory()->for($other)->create();

    $this->actingAs($this->driver)
        ->postJson(route('api.driver.rides.store'), ridePayload([
            'user_id' => $other->id,
            'vehicle_id' => $otherVehicle->id,
        ]))
        ->assertStatus(Response::HTTP_CREATED);

    $ride = Ride::query()->sole();

    expect($ride->user_id)->toBe($this->driver->id)
        ->and($ride->vehicle_id)->toBe($this->vehicle->id);
});

it('lists the drivers own upcoming rides, soonest first', function () {
    $later = Ride::factory()->forDriver($this->driver)
        ->departingAt(Carbon::now()->addDays(2))->create();

    $sooner = Ride::factory()->forDriver($this->driver)
        ->departingAt(Carbon::now()->addHours(4))->create();

    // Already left, so it is no longer upcoming.
    Ride::factory()->forDriver($this->driver)
        ->departingAt(Carbon::now()->subDay())->create();

    // Somebody else's ride.
    Ride::factory()->create();

    $this->actingAs($this->driver)
        ->getJson(route('api.driver.rides.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data.rides')
        ->assertJsonPath('data.rides.0.id', $sooner->id)
        ->assertJsonPath('data.rides.1.id', $later->id);
});

it('answers an empty list before anything is published', function () {
    $this->actingAs($this->driver)
        ->getJson(route('api.driver.rides.index'))
        ->assertOk()
        ->assertJsonPath('message', 'No upcoming rides.')
        ->assertJsonCount(0, 'data.rides');
});

it('edits a ride nobody has booked, field by field', function () {
    $ride = Ride::factory()->forDriver($this->driver)->create(['seat_price' => 650]);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), ['seat_price' => '700.00'])
        ->assertOk()
        ->assertJsonPath('data.ride.seat_price', '700.00')
        // Everything not sent is left exactly as it was.
        ->assertJsonPath('data.ride.origin.label', 'Gulshan 1, Dhaka')
        ->assertJsonPath('data.ride.destination.label', 'GEC Circle, Chattogram')
        ->assertJsonPath('data.ride.seats_offered', 4);

    expect($ride->fresh()->seat_price)->toBe('700.00');
});

it('moves one end of the trip and the departure', function () {
    $ride = Ride::factory()
        ->forDriver($this->driver)
        ->between($this->route, $this->sonaimuri, $this->dhaka)
        ->create();

    $departsAt = Carbon::now()->addDays(2);

    // Only the start moves; the destination is read off the ride and the
    // pair re-placed, which is what keeps the sequences consistent.
    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), [
            'origin_stop_id' => $this->laksam->id,
            'departs_at' => $departsAt->toIso8601String(),
            'seats_offered' => 6,
        ])
        ->assertOk()
        ->assertJsonPath('data.ride.origin.stop_id', $this->laksam->id)
        ->assertJsonPath('data.ride.origin.label', 'Laksam')
        ->assertJsonPath('data.ride.destination.label', 'Dhaka')
        ->assertJsonPath('data.ride.seats_offered', 6);

    $ride = $ride->fresh();

    expect($ride->departs_at->toDateTimeString())->toBe($departsAt->toDateTimeString())
        ->and($ride->origin_sequence)->toBe(30)
        ->and($ride->destination_sequence)->toBe(10);
});

it('re-places a ride whose new end puts it on another corridor', function () {
    $ride = Ride::factory()
        ->forDriver($this->driver)
        ->between($this->route, $this->cumilla, $this->dhaka)
        ->create();

    $feni = Stop::factory()->create(['name' => 'Feni']);

    $chattogram = TravelRoute::factory()
        ->along($this->dhaka, $this->cumilla, $feni)
        ->create(['name' => 'Dhaka - Chattogram']);

    // Feni is only on the Chattogram highway, so the trip moves road.
    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), [
            'origin_stop_id' => $feni->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.ride.route.id', $chattogram->id);

    expect($ride->fresh()->travel_route_id)->toBe($chattogram->id);
});

it('refuses an edit that leaves the trip off every route', function () {
    $ride = Ride::factory()
        ->forDriver($this->driver)
        ->between($this->route, $this->sonaimuri, $this->dhaka)
        ->create();

    $sylhet = Stop::factory()->create(['name' => 'Sylhet']);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), [
            'destination_stop_id' => $sylhet->id,
        ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('destination_stop_id');

    expect($ride->fresh()->destination_stop_id)->toBe($this->dhaka->id);
});

it('asks for both ends when moving a ride that predates routes', function () {
    // The factory default is the shape of a ride published before corridors
    // existed: no stops, so there is no other end to hold the edit against.
    $ride = Ride::factory()->forDriver($this->driver)->create();

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), [
            'origin_stop_id' => $this->sonaimuri->id,
        ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('origin_stop_id');

    expect($ride->fresh()->travel_route_id)->toBeNull();
});

it('refuses an edit once somebody has booked', function () {
    $ride = Ride::factory()->forDriver($this->driver)->create(['seat_price' => 650]);

    Booking::factory()->for($ride)->create([
        'user_id' => User::factory()->passenger(),
    ]);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), ['seat_price' => '900.00'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('ride');

    // The fare a passenger agreed to is still the fare.
    expect($ride->fresh()->seat_price)->toBe('650.00');
});

it('answers a 404 for another drivers ride', function () {
    $ride = Ride::factory()->create();

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), ['seat_price' => '900.00'])
        ->assertNotFound();

    expect($ride->fresh()->seat_price)->not->toBe('900.00');
});

it('holds an edited seat count inside the vehicles passenger seats', function () {
    $ride = Ride::factory()->forDriver($this->driver)->create();

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), ['seats_offered' => 12])
        ->assertJsonValidationErrorFor('seats_offered');

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), ['seats_offered' => 11])
        ->assertOk();
});

it('checks a moved destination against the start it is not sending', function () {
    $ride = Ride::factory()
        ->forDriver($this->driver)
        ->between($this->route, $this->sonaimuri, $this->dhaka)
        ->create();

    // Only the destination is sent, and it lands on the ride's own start.
    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), [
            'destination_stop_id' => $ride->origin_stop_id,
        ])
        ->assertJsonValidationErrorFor('destination_stop_id');
});

it('reports the seats booked and the seats still free', function () {
    $ride = Ride::factory()->forDriver($this->driver)->create(['seats_offered' => 6]);

    Booking::factory()->for($ride)->ofSeats(2)->create(['user_id' => User::factory()->passenger()]);
    Booking::factory()->for($ride)->ofSeats(1)->create(['user_id' => User::factory()->passenger()]);

    $this->actingAs($this->driver)
        ->getJson(route('api.driver.rides.index'))
        ->assertOk()
        ->assertJsonPath('data.rides.0.seats_offered', 6)
        ->assertJsonPath('data.rides.0.seats_booked', 3)
        ->assertJsonPath('data.rides.0.seats_available', 3);
});

it('keeps a passenger and an admin off both endpoints', function () {
    foreach ([User::factory()->passenger()->create(), User::factory()->admin()->create()] as $user) {
        $this->actingAs($user)
            ->getJson(route('api.driver.rides.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('api.driver.rides.store'), ridePayload())
            ->assertForbidden();
    }

    expect(Ride::query()->count())->toBe(0);
});

it('rolls the whole edit back when the write fails', function () {
    $ride = Ride::factory()->forDriver($this->driver)->create(['seat_price' => 650]);

    // Checking for bookings and then writing is one step or it is nothing.
    $this->app->bind(RideRepository::class, fn () => new class extends RideEloquentRepository
    {
        public function update(Ride $ride, array $attributes): Ride
        {
            $updated = parent::update($ride, $attributes);

            throw new RuntimeException('the write failed after the row was written');
        }
    });

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', $ride), ['seat_price' => '900.00'])
        ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);

    expect($ride->fresh()->seat_price)->toBe('650.00');
});

it('keeps an unverified driver from publishing or editing', function () {
    $unverified = User::factory()->driver()->create();
    Vehicle::factory()->for($unverified)->create();

    // A stranger gets into this car, so the documents behind the trip have
    // to have been reviewed first.
    $this->actingAs($unverified)
        ->postJson(route('api.driver.rides.store'), ridePayload())
        ->assertForbidden()
        ->assertJsonPath('data.verification_status', 'Unverified');

    $ride = Ride::factory()->forDriver($unverified)->create();

    $this->actingAs($unverified)
        ->patchJson(route('api.driver.rides.update', $ride), ['seat_price' => '900.00'])
        ->assertForbidden();

    expect(Ride::query()->count())->toBe(1);
});

it('keeps a driver whose documents are still in review from publishing', function () {
    $pending = User::factory()->driver()->awaitingVerification()->create();
    Vehicle::factory()->for($pending)->create();

    // Submitted is not reviewed: the badge is what the gate reads.
    $this->actingAs($pending)
        ->postJson(route('api.driver.rides.store'), ridePayload())
        ->assertForbidden()
        ->assertJsonPath('data.verification_status', 'Pending');

    expect(Ride::query()->count())->toBe(0);
});

it('still lists the rides of a driver whose badge has lapsed', function () {
    $rejected = User::factory()->driver()->create();
    Ride::factory()->forDriver($rejected)->create();

    // Listing is open on purpose - they can see what they already have out
    // there even while they cannot publish more.
    $this->actingAs($rejected)
        ->getJson(route('api.driver.rides.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.rides');
});

it('refuses an unauthenticated request', function () {
    $this->postJson(route('api.driver.rides.store'), ridePayload())
        ->assertUnauthorized();

    $this->getJson(route('api.driver.rides.index'))
        ->assertUnauthorized();
});
