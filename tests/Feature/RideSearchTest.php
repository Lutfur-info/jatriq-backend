<?php

use App\Models\Booking;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Models\User;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/*
 * The search a passenger actually makes: "I am at Laksam and I want Dhaka -
 * who is coming past?". The answer has to include the vehicle that set out
 * from Sonaimuri, because Laksam is still ahead of it on the same road.
 *
 * Two corridors, sharing the stretch between Dhaka and Cumilla exactly as the
 * real ones do. Sequence runs outbound from Dhaka, so a trip toward Dhaka
 * runs down the numbers.
 *
 *   Noakhali     Dhaka 10 - Cumilla 20 - Laksam 30 - Sonaimuri 40
 *   Chattogram   Dhaka 10 - Cumilla 20 - Feni 30
 */
beforeEach(function () {
    $this->dhaka = Stop::factory()->create(['name' => 'Dhaka']);
    $this->cumilla = Stop::factory()->create(['name' => 'Cumilla']);
    $this->laksam = Stop::factory()->create(['name' => 'Laksam']);
    $this->sonaimuri = Stop::factory()->create(['name' => 'Sonaimuri']);
    $this->feni = Stop::factory()->create(['name' => 'Feni']);

    $this->noakhali = TravelRoute::factory()
        ->along($this->dhaka, $this->cumilla, $this->laksam, $this->sonaimuri)
        ->create(['name' => 'Dhaka - Noakhali']);

    $this->chattogram = TravelRoute::factory()
        ->along($this->dhaka, $this->cumilla, $this->feni)
        ->create(['name' => 'Dhaka - Chattogram']);
});

/**
 * A bookable ride running between two stops on a corridor.
 */
function rideBetween(TravelRoute $route, Stop $origin, Stop $destination, int $seats = 4): Ride
{
    return Ride::factory()
        ->between($route, $origin, $destination)
        ->departingAt(Carbon::now()->addHours(3))
        ->create(['seats_offered' => $seats]);
}

it('finds a ride that started before the passenger joins it', function () {
    // The worked example. The vehicle left Sonaimuri; Laksam is the next
    // town down the road, so its seats are available to somebody there.
    $ride = rideBetween($this->noakhali, $this->sonaimuri, $this->dhaka);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.rides')
        ->assertJsonPath('data.rides.0.id', $ride->id)
        ->assertJsonPath('data.rides.0.origin.label', 'Sonaimuri');
});

it('leaves out a ride that starts after the passenger wants to board', function () {
    // This one joins the road at Cumilla, which is past Laksam - it never
    // goes near the passenger.
    rideBetween($this->noakhali, $this->cumilla, $this->dhaka);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'data.rides')
        ->assertJsonPath('message', 'No rides running that way right now.');
});

it('leaves out a ride running the other way down the same corridor', function () {
    // Same road, same two towns, opposite direction.
    rideBetween($this->noakhali, $this->dhaka, $this->sonaimuri);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'data.rides');
});

it('serves a passenger getting off before the ride does', function () {
    // Laksam to Cumilla is a leg inside Sonaimuri to Dhaka, so the driver
    // passing through can sell that hop.
    $ride = rideBetween($this->noakhali, $this->sonaimuri, $this->dhaka);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->cumilla->id,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.rides')
        ->assertJsonPath('data.rides.0.id', $ride->id);
});

it('leaves out a ride that ends before the passenger does', function () {
    // It turns off at Cumilla; the passenger wants to reach Dhaka.
    rideBetween($this->noakhali, $this->sonaimuri, $this->cumilla);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'data.rides');
});

it('finds rides on every corridor that shares the stretch being travelled', function () {
    /*
     * Cumilla to Dhaka is the same piece of road for both corridors, so a
     * passenger standing at Cumilla should be offered the Noakhali vehicle
     * and the Chattogram one alike - and this is the case that breaks if
     * stops are owned by one route instead of shared.
     */
    $fromNoakhali = rideBetween($this->noakhali, $this->sonaimuri, $this->dhaka);
    $fromChattogram = rideBetween($this->chattogram, $this->feni, $this->dhaka);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->cumilla->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertJsonCount(2, 'data.rides')
        ->assertJsonPath('data.rides.0.id', $fromNoakhali->id)
        ->assertJsonPath('data.rides.1.id', $fromChattogram->id);
});

it('leaves out a ride on a corridor the journey is not on', function () {
    // Feni is only on the Chattogram highway, so this vehicle never reaches
    // Laksam however far down the shared stretch it travels.
    rideBetween($this->chattogram, $this->feni, $this->dhaka);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'data.rides');
});

it('answers nothing when no route runs between the two stops', function () {
    rideBetween($this->noakhali, $this->sonaimuri, $this->dhaka);

    $unconnected = Stop::factory()->create(['name' => 'Sylhet']);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->sonaimuri->id,
        'to_stop_id' => $unconnected->id,
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'data.rides');
});

it('still respects the departure and the seats left while searching', function () {
    $bookable = rideBetween($this->noakhali, $this->sonaimuri, $this->dhaka);

    // Already gone.
    Ride::factory()
        ->between($this->noakhali, $this->sonaimuri, $this->dhaka)
        ->departingAt(Carbon::now()->subHour())
        ->create(['seats_offered' => 4]);

    // Sold out.
    $full = rideBetween($this->noakhali, $this->sonaimuri, $this->dhaka, seats: 2);
    Booking::factory()->for($full)->ofSeats(2)->create(['user_id' => User::factory()->passenger()]);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.rides')
        ->assertJsonPath('data.rides.0.id', $bookable->id);
});

it('returns every bookable ride when no journey is named', function () {
    $onRoute = rideBetween($this->noakhali, $this->sonaimuri, $this->dhaka);

    // The shape of a ride published before corridors existed.
    $legacy = Ride::factory()
        ->departingAt(Carbon::now()->addHours(4))
        ->create(['seats_offered' => 4]);

    $this->getJson(route('api.rides.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data.rides')
        ->assertJsonPath('data.rides.0.id', $onRoute->id)
        ->assertJsonPath('data.rides.1.id', $legacy->id);
});

it('never matches a ride that predates routes', function () {
    Ride::factory()
        ->departingAt(Carbon::now()->addHours(4))
        ->create(['seats_offered' => 4]);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'data.rides');
});

it('insists on both ends of the journey or neither', function () {
    $this->getJson(route('api.rides.index', ['from_stop_id' => $this->laksam->id]))
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('to_stop_id');

    $this->getJson(route('api.rides.index', ['to_stop_id' => $this->dhaka->id]))
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('from_stop_id');
});

it('refuses a journey that begins and ends in the same place', function () {
    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->laksam->id,
    ]))
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('to_stop_id');
});

it('searches from a retired stop, which new rides may not be offered from', function () {
    // Retiring a stop must not hide the rides already published past it.
    $ride = rideBetween($this->noakhali, $this->sonaimuri, $this->dhaka);

    $this->laksam->update(['is_active' => false]);

    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertJsonPath('data.rides.0.id', $ride->id);
});

it('serves the corridors and their stops in travel order, to nobody in particular', function () {
    // Unauthenticated, like the ride list beside it: the pickers are drawn
    // before anybody has an account.
    $this->getJson(route('api.routes.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data.routes')
        // Ordered by name, so Chattogram comes first.
        ->assertJsonPath('data.routes.0.name', 'Dhaka - Chattogram')
        ->assertJsonPath('data.routes.1.name', 'Dhaka - Noakhali')
        ->assertJsonPath('data.routes.1.stops.0.name', 'Dhaka')
        ->assertJsonPath('data.routes.1.stops.0.sequence', 10)
        ->assertJsonPath('data.routes.1.stops.3.name', 'Sonaimuri')
        ->assertJsonPath('data.routes.1.stops.3.sequence', 40);
});

it('keeps a retired stop and a retired corridor out of the pickers', function () {
    $this->laksam->update(['is_active' => false]);
    $this->chattogram->update(['is_active' => false]);

    $this->getJson(route('api.routes.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.routes')
        ->assertJsonPath('data.routes.0.name', 'Dhaka - Noakhali')
        ->assertJsonCount(3, 'data.routes.0.stops')
        ->assertJsonMissing(['name' => 'Laksam']);
});
