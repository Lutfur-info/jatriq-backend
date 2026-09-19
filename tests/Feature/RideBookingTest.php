<?php

use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use App\Repositories\Contracts\BookingRepository;
use App\Repositories\Eloquent\BookingEloquentRepository;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    // Booking needs the identity badge: the driver is letting a stranger
    // into their car. The refusals without it are their own tests below.
    $this->passenger = User::factory()->passenger()->verified()->create();

    $this->ride = Ride::factory()->create(['seats_offered' => 4]);
});

it('lists the rides a passenger can still book, soonest first', function () {
    $soon = Ride::factory()->departingAt(Carbon::now()->addHours(2))->create(['seats_offered' => 4]);
    $later = Ride::factory()->departingAt(Carbon::now()->addDays(2))->create(['seats_offered' => 4]);

    // Gone, so not an option.
    Ride::factory()->departingAt(Carbon::now()->subHour())->create(['seats_offered' => 4]);

    // Full, so not an option either.
    $full = Ride::factory()->departingAt(Carbon::now()->addHours(3))->create(['seats_offered' => 2]);
    Booking::factory()->for($full)->ofSeats(2)->create(['user_id' => User::factory()->passenger()]);

    // Partly booked is still bookable.
    $partly = Ride::factory()->departingAt(Carbon::now()->addHours(4))->create(['seats_offered' => 4]);
    Booking::factory()->for($partly)->ofSeats(3)->create(['user_id' => User::factory()->passenger()]);

    // The shared fixture ride leaves in a day, so it sorts third.
    $this->actingAs($this->passenger)
        ->getJson(route('api.rides.index'))
        ->assertOk()
        ->assertJsonPath('message', 'Rides you can book.')
        ->assertJsonCount(4, 'data.rides')
        ->assertJsonPath('data.rides.0.id', $soon->id)
        ->assertJsonPath('data.rides.1.id', $partly->id)
        ->assertJsonPath('data.rides.2.id', $this->ride->id)
        ->assertJsonPath('data.rides.3.id', $later->id)
        // The seat counts have to be honest, or she books into a full car.
        ->assertJsonPath('data.rides.1.seats_booked', 3)
        ->assertJsonPath('data.rides.1.seats_available', 1)
        // The car comes along so the whole card renders in one call.
        ->assertJsonPath('data.rides.0.vehicle.registration_number', $soon->vehicle->registration_number);
});

it('answers an empty available list when nothing is bookable', function () {
    // The shared fixture ride is bookable, so it has to go for this one.
    $this->ride->delete();

    Ride::factory()->departingAt(Carbon::now()->subHour())->create();

    $this->actingAs($this->passenger)
        ->getJson(route('api.rides.index'))
        ->assertOk()
        ->assertJsonPath('message', 'No rides available right now.')
        ->assertJsonCount(0, 'data.rides');
});

it('shows the available rides to anybody at all, signed in or not', function () {
    // The home screen is a shop window: somebody with no account sees what
    // is on offer, and signing in is what booking asks for.
    $this->getJson(route('api.rides.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.rides');

    foreach ([
        User::factory()->passenger()->create(),
        User::factory()->driver()->verified()->create(),
        User::factory()->admin()->verified()->create(),
    ] as $user) {
        $this->actingAs($user)
            ->getJson(route('api.rides.index'))
            ->assertOk();
    }
});

it('still makes booking a seat need an account, a role and the badge', function () {
    // Browsing is open; taking a seat is not.
    $this->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 1])
        ->assertUnauthorized();

    $unverified = User::factory()->passenger()->create();

    $this->actingAs($unverified)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 1])
        ->assertForbidden();

    expect(Booking::query()->count())->toBe(0);
});

it('tells nobody who is driving', function () {
    // Public endpoint: the ride, the car and the seats, never the person.
    $body = $this->getJson(route('api.rides.index'))->assertOk()->json('data.rides.0');

    expect($body)->not->toHaveKeys(['user', 'driver', 'user_id']);
});

it('books seats on a ride for the passenger', function () {
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 2])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('message', 'Seats booked.')
        ->assertJsonPath('data.booking.seats', 2)
        // The ride comes back with its inventory, so the client does not
        // have to work out what is left.
        ->assertJsonPath('data.ride.seats_offered', 4)
        ->assertJsonPath('data.ride.seats_booked', 2)
        ->assertJsonPath('data.ride.seats_available', 2);

    $booking = Booking::query()->sole();

    expect($booking->ride_id)->toBe($this->ride->id)
        ->and($booking->user_id)->toBe($this->passenger->id)
        ->and($booking->seats)->toBe(2);
});

it('tops up the booking the passenger already holds', function () {
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 2])
        ->assertStatus(Response::HTTP_CREATED);

    // The second request adds to the same row rather than making another.
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 1])
        ->assertOk()
        ->assertJsonPath('message', 'Seats added to your booking.')
        ->assertJsonPath('data.booking.seats', 3)
        ->assertJsonPath('data.ride.seats_available', 1);

    expect(Booking::query()->count())->toBe(1)
        ->and(Booking::query()->sole()->seats)->toBe(3);
});

it('counts the seats every passenger has taken', function () {
    Booking::factory()->for($this->ride)->ofSeats(3)->create([
        'user_id' => User::factory()->passenger(),
    ]);

    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 1])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.ride.seats_booked', 4)
        ->assertJsonPath('data.ride.seats_available', 0);
});

it('refuses more seats than are still free', function () {
    Booking::factory()->for($this->ride)->ofSeats(3)->create([
        'user_id' => User::factory()->passenger(),
    ]);

    // One left, two asked for.
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 2])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('seats')
        ->assertJsonPath('errors.seats.0', 'Only 1 of the seats on this ride are still free.');

    expect(Booking::query()->count())->toBe(1);
});

it('refuses a ride that is already full', function () {
    Booking::factory()->for($this->ride)->ofSeats(4)->create([
        'user_id' => User::factory()->passenger(),
    ]);

    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 1])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonPath('errors.seats.0', 'This ride is full.');
});

it('refuses more seats than the ride ever offered', function () {
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 5])
        ->assertJsonValidationErrorFor('seats')
        ->assertJsonPath('errors.seats.0', 'This ride only has 4 passenger seats.');
});

it('requires a sensible seat count', function () {
    $this->actingAs($this->passenger);

    foreach ([[], ['seats' => 0], ['seats' => -1], ['seats' => 'two'], ['seats' => 1.5]] as $body) {
        $this->postJson(route('api.rides.bookings.store', $this->ride), $body)
            ->assertJsonValidationErrorFor('seats');
    }

    expect(Booking::query()->count())->toBe(0);
});

it('refuses a ride that has already left', function () {
    $gone = Ride::factory()->departingAt(Carbon::now()->subHour())->create();

    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $gone), ['seats' => 1])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('ride');

    expect(Booking::query()->count())->toBe(0);
});

it('keeps an unverified passenger from booking', function () {
    $unverified = User::factory()->passenger()->create();

    $this->actingAs($unverified)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 1])
        ->assertForbidden()
        ->assertJsonPath('data.verification_status', 'Unverified');

    $pending = User::factory()->passenger()->awaitingVerification()->create();

    $this->actingAs($pending)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 1])
        ->assertForbidden()
        ->assertJsonPath('data.verification_status', 'Pending');

    expect(Booking::query()->count())->toBe(0);
});

it('keeps a driver and an admin from booking at all', function () {
    // Booking is the API's one Passenger-only endpoint: a driver offers
    // seats, a passenger takes them - which is also what stops a driver
    // booking their own ride.
    foreach ([
        User::factory()->driver()->verified()->create(),
        User::factory()->admin()->verified()->create(),
    ] as $user) {
        $this->actingAs($user)
            ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 1])
            ->assertForbidden();
    }

    expect(Booking::query()->count())->toBe(0);
});

it('leaves a booking untouched when the top-up is refused', function () {
    Booking::factory()->for($this->ride)->ofSeats(1)->create([
        'user_id' => User::factory()->passenger(),
    ]);

    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 2])
        ->assertStatus(Response::HTTP_CREATED);

    // Two of the four are gone and this passenger holds two of them. Asking
    // for three more is refused, and must not leave them holding some of it.
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 3])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrorFor('seats');

    $mine = Booking::query()->where('user_id', $this->passenger->id)->sole();

    expect($mine->seats)->toBe(2)
        ->and((int) Booking::query()->sum('seats'))->toBe(3);
});

it('rolls the whole booking back when the write fails', function () {
    // The transaction is the point: a write that blows up after touching the
    // row must leave nothing behind. Swapped in through the contract, which
    // is what the repository layer exists to allow.
    $this->app->bind(BookingRepository::class, fn () => new class extends BookingEloquentRepository
    {
        public function put(Ride $ride, User $user, int $seats): Booking
        {
            $booking = parent::put($ride, $user, $seats);

            throw new RuntimeException('the write failed after the row was written');
        }
    });

    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 2])
        ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);

    expect(Booking::query()->count())->toBe(0);
});

it('shows the passenger every booking she holds, upcoming trips first', function () {
    $soon = Ride::factory()->departingAt(Carbon::now()->addHours(5))->create(['seats_offered' => 4]);
    $later = Ride::factory()->departingAt(Carbon::now()->addDays(3))->create(['seats_offered' => 4]);
    $lastWeek = Ride::factory()->departingAt(Carbon::now()->subWeek())->create(['seats_offered' => 4]);
    $yesterday = Ride::factory()->departingAt(Carbon::now()->subDay())->create(['seats_offered' => 4]);

    foreach ([$soon, $later, $lastWeek, $yesterday] as $ride) {
        Booking::factory()->for($ride)->ofSeats(2)->create(['user_id' => $this->passenger->id]);
    }

    // Somebody else's booking, which is none of her business.
    Booking::factory()->create();

    $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonPath('message', 'Your bookings.')
        ->assertJsonCount(4, 'data.bookings')
        // Upcoming soonest-first, then the trips already taken, latest first.
        ->assertJsonPath('data.bookings.0.destination_name', $soon->destination_label)
        ->assertJsonPath('data.bookings.1.destination_name', $later->destination_label)
        ->assertJsonPath('data.bookings.2.destination_name', $yesterday->destination_label)
        ->assertJsonPath('data.bookings.3.destination_name', $lastWeek->destination_label);
});

it('shows the seats, the total, the vehicle number and both place names', function () {
    $ride = Ride::factory()
        ->departingAt(Carbon::now()->addHours(5))
        ->create(['seats_offered' => 4, 'seat_price' => '650.00']);

    Booking::factory()->for($ride)->ofSeats(2)->create(['user_id' => $this->passenger->id]);

    $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonPath('data.bookings.0.seats', 2)
        // 2 x 650.00, in fixed point.
        ->assertJsonPath('data.bookings.0.total_amount', '1300.00')
        ->assertJsonPath('data.bookings.0.vehicle_number', $ride->vehicle->registration_number)
        ->assertJsonPath('data.bookings.0.origin_name', $ride->origin_label)
        ->assertJsonPath('data.bookings.0.destination_name', $ride->destination_label);
});

it('totals a fare with paisa in it exactly', function () {
    // Float arithmetic would make this 1301.0000000000002.
    $ride = Ride::factory()
        ->departingAt(Carbon::now()->addHours(5))
        ->create(['seats_offered' => 4, 'seat_price' => '650.50']);

    Booking::factory()->for($ride)->ofSeats(2)->create(['user_id' => $this->passenger->id]);

    $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonPath('data.bookings.0.total_amount', '1301.00');
});

it('totals the top-up, not just the first booking', function () {
    $ride = Ride::factory()
        ->departingAt(Carbon::now()->addHours(5))
        ->create(['seats_offered' => 6, 'seat_price' => '300.00']);

    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $ride), ['seats' => 2])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.booking.total_amount', '600.00');

    // Three seats in total, so the amount owed follows.
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', $ride), ['seats' => 1])
        ->assertOk()
        ->assertJsonPath('data.booking.seats', 3)
        ->assertJsonPath('data.booking.total_amount', '900.00');

    $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonPath('data.bookings.0.total_amount', '900.00');
});

it('answers an empty list before she has booked anything', function () {
    $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonPath('message', 'No bookings yet.')
        ->assertJsonCount(0, 'data.bookings');
});

it('still lists the bookings of a passenger whose badge has lapsed', function () {
    $unverified = User::factory()->passenger()->create();
    Booking::factory()->for($this->ride)->create(['user_id' => $unverified->id]);

    // She cannot book another seat, but what she already paid for stays
    // visible - the same bargain the driver's own ride list makes.
    $this->actingAs($unverified)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.bookings');
});

it('keeps a driver and an admin off the bookings list', function () {
    foreach ([
        User::factory()->driver()->verified()->create(),
        User::factory()->admin()->verified()->create(),
    ] as $user) {
        $this->actingAs($user)
            ->getJson(route('api.bookings.index'))
            ->assertForbidden();
    }
});

it('refuses an unauthenticated bookings list', function () {
    $this->getJson(route('api.bookings.index'))->assertUnauthorized();
});

it('refuses an unauthenticated booking', function () {
    $this->postJson(route('api.rides.bookings.store', $this->ride), ['seats' => 1])
        ->assertUnauthorized();
});

it('answers a 404 for a ride that does not exist', function () {
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', 9999), ['seats' => 1])
        ->assertNotFound();
});
