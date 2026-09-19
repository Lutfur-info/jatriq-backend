<?php

use App\Models\Booking;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/*
 * The public web board: the site's front page, the search over it, and the
 * ride behind every card. It reads the same rides `GET /api/rides` does and
 * answers the same journey question - the difference is that a browser gets
 * them a page at a time.
 *
 *   Noakhali     Dhaka 10 - Cumilla 20 - Laksam 30 - Sonaimuri 40
 */
beforeEach(function () {
    // The page component is compiled by Vite, and the built manifest predates
    // it; nothing here is asserting about assets.
    $this->withoutVite();

    $this->dhaka = Stop::factory()->create(['name' => 'Dhaka']);
    $this->cumilla = Stop::factory()->create(['name' => 'Cumilla']);
    $this->laksam = Stop::factory()->create(['name' => 'Laksam']);
    $this->sonaimuri = Stop::factory()->create(['name' => 'Sonaimuri']);

    $this->noakhali = TravelRoute::factory()
        ->along($this->dhaka, $this->cumilla, $this->laksam, $this->sonaimuri)
        ->create(['name' => 'Dhaka - Noakhali']);
});

/**
 * A bookable ride running between two stops on the corridor.
 */
function boardRide(
    TravelRoute $route,
    Stop $origin,
    Stop $destination,
    Carbon $departsAt,
    int $seats = 4,
): Ride {
    return Ride::factory()
        ->between($route, $origin, $destination)
        ->departingAt($departsAt)
        ->create(['seats_offered' => $seats]);
}

it('shows the upcoming rides on the front page, soonest first', function () {
    $later = boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addDay());
    $soonest = boardRide($this->noakhali, $this->cumilla, $this->dhaka, Carbon::now()->addHours(2));

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rides/index')
            ->has('rides.data', 2)
            ->where('rides.data.0.id', $soonest->id)
            ->where('rides.data.1.id', $later->id)
            ->where('rides.data.0.seats_available', 4)
            ->where('filters.from_stop_id', null)
        );
});

it('breaks the board into pages', function () {
    config(['rides.board.per_page' => 2]);

    foreach (range(1, 3) as $hours) {
        boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addHours($hours));
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rides/index')
            ->has('rides.data', 2)
            ->where('rides.meta.total', 3)
            ->where('rides.meta.last_page', 2)
            ->where('rides.meta.current_page', 1)
        );

    $this->get(route('home', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rides.data', 1)
            ->where('rides.meta.current_page', 2)
        );
});

it('keeps the journey on the second page of a search', function () {
    config(['rides.board.per_page' => 1]);

    boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addHours(2));
    $second = boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addHours(5));

    // Same corridor, wrong way - it must not be paged into the results.
    boardRide($this->noakhali, $this->dhaka, $this->sonaimuri, Carbon::now()->addHours(3));

    $this->get(route('home', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
        'page' => 2,
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rides.data', 1)
            ->where('rides.data.0.id', $second->id)
            ->where('rides.meta.total', 2)
            ->where('filters.from_stop_id', $this->laksam->id)
            ->where('filters.to_stop_id', $this->dhaka->id)
        );
});

it('searches the board for the ride that will pass the passenger', function () {
    // The worked example: the vehicle left Sonaimuri, and Laksam is still
    // ahead of it.
    $passing = boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addHours(3));

    // Joins the road past Laksam, so it never goes near the passenger.
    boardRide($this->noakhali, $this->cumilla, $this->dhaka, Carbon::now()->addHours(4));

    $this->get(route('home', [
        'from_stop_id' => $this->laksam->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rides.data', 1)
            ->where('rides.data.0.id', $passing->id)
            ->where('rides.data.0.origin.label', 'Sonaimuri')
        );
});

it('pages an empty board when no corridor carries the journey', function () {
    // Off the network entirely, so `legsBetween()` answers with nothing -
    // which must page as "no rides", never as "every ride".
    $offNetwork = Stop::factory()->create(['name' => 'Saint Martin']);

    boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addHours(3));

    $this->get(route('home', [
        'from_stop_id' => $offNetwork->id,
        'to_stop_id' => $this->dhaka->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rides.data', 0)
            ->where('rides.meta.total', 0)
        );
});

it('leaves a departed ride and a full one off the board', function () {
    $bookable = boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addHours(3));

    boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->subHour());

    $full = boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addHours(4), seats: 2);
    Booking::factory()->for($full)->ofSeats(2)->create(['user_id' => User::factory()->passenger()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rides.data', 1)
            ->where('rides.data.0.id', $bookable->id)
        );
});

it('ships the corridors the pickers are built from', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('routes', 1)
            ->where('routes.0.name', 'Dhaka - Noakhali')
            ->has('routes.0.stops', 4)
            // In travel order, outbound from Dhaka - never alphabetical.
            ->where('routes.0.stops.0.name', 'Dhaka')
            ->where('routes.0.stops.3.name', 'Sonaimuri')
        );
});

it('refuses a journey with only one end named', function () {
    $this->get(route('home', ['from_stop_id' => $this->laksam->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('to_stop_id');
});

it('shows one ride in full', function () {
    $ride = boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addHours(3));

    $this->get(route('rides.show', $ride))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rides/show')
            ->where('ride.id', $ride->id)
            ->where('ride.origin.label', 'Sonaimuri')
            ->where('ride.destination.label', 'Dhaka')
            ->where('ride.route.name', 'Dhaka - Noakhali')
            ->where('ride.seats_available', 4)
            ->has('ride.vehicle')
        );
});

it('still shows a ride whose seats are all taken', function () {
    // Off the board, but somebody holding the link is owed "fully booked"
    // rather than being told the trip never existed.
    $full = boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->addHours(3), seats: 2);
    Booking::factory()->for($full)->ofSeats(2)->create(['user_id' => User::factory()->passenger()]);

    $this->get(route('rides.show', $full))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ride.seats_booked', 2)
            ->where('ride.seats_available', 0)
        );
});

it('has no page for a ride that has already left', function () {
    $departed = boardRide($this->noakhali, $this->sonaimuri, $this->dhaka, Carbon::now()->subHour());

    $this->get(route('rides.show', $departed))->assertNotFound();
});

it('has no page for a ride that does not exist', function () {
    $this->get(route('rides.show', 404))->assertNotFound();
});

it('never publishes the driver on either public page', function () {
    $driver = User::factory()->driver()->create([
        'first_name' => 'Zannatul',
        'last_name' => 'Ferdous',
        'msisdn' => '01911223344',
    ]);

    $ride = Ride::factory()
        ->forDriver($driver)
        ->between($this->noakhali, $this->sonaimuri, $this->dhaka)
        ->departingAt(Carbon::now()->addHours(3))
        ->create(['seats_offered' => 4]);

    foreach ([route('home'), route('rides.show', $ride)] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertDontSee('Zannatul')
            ->assertDontSee('01911223344');
    }
});
