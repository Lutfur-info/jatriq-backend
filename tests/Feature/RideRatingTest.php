<?php

use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RatingService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/*
 * What a passenger thought of a trip she took.
 *
 * There is no ride lifecycle in this product - no "complete" to press and
 * nothing to press it - so "she took the trip" is read off what already
 * exists: the driver confirmed her seat, and the ride has departed.
 */
beforeEach(function () {
    $this->passenger = User::factory()->passenger()->verified()->create();
    $this->driver = User::factory()->driver()->verified()->create();
    Vehicle::factory()->for($this->driver)->create();

    // A trip already taken: departed, and the driver confirmed her seat.
    $this->taken = Ride::factory()
        ->forDriver($this->driver)
        ->departingAt(Carbon::now()->subDay())
        ->create(['seats_offered' => 4]);

    $this->booking = Booking::factory()
        ->for($this->taken)
        ->for($this->passenger)
        ->confirmed()
        ->create();
});

/**
 * Post a score for a booking as its passenger.
 */
function rateTrip(User $passenger, Booking $booking, int $rating): TestResponse
{
    return test()->actingAs($passenger)
        ->postJson(route('api.bookings.rating.store', ['booking' => $booking]), [
            'rating' => $rating,
        ]);
}

it('rates a trip she has taken', function () {
    rateTrip($this->passenger, $this->booking, 5)
        ->assertOk()
        ->assertJsonPath('message', 'Thanks for rating this trip.')
        ->assertJsonPath('data.booking.rating', 5)
        ->assertJsonPath('data.booking.can_rate', true);

    $this->booking->refresh();

    expect($this->booking->rating)->toBe(5)
        ->and($this->booking->rated_at)->not->toBeNull();
});

it('moves the driver\'s score, derived rather than assigned', function () {
    rateTrip($this->passenger, $this->booking, 4)->assertOk();

    $this->driver->refresh();

    expect($this->driver->rating_average)->toBe('4.00')
        ->and($this->driver->ratings_count)->toBe(1)
        ->and($this->driver->hasRating())->toBeTrue();

    // A second passenger on the same driver, on another departed ride.
    $second = User::factory()->passenger()->verified()->create();
    $another = Ride::factory()
        ->forDriver($this->driver)
        ->departingAt(Carbon::now()->subDays(2))
        ->create(['seats_offered' => 4]);

    rateTrip($second, Booking::factory()->for($another)->for($second)->confirmed()->create(), 3)
        ->assertOk();

    expect($this->driver->refresh()->rating_average)->toBe('3.50')
        ->and($this->driver->ratings_count)->toBe(2);
});

it('reads no rating at all before anybody scores him', function () {
    // Null, never zero: a new driver and a badly rated one must not look
    // alike to a passenger choosing between them.
    expect($this->driver->refresh()->rating_average)->toBeNull()
        ->and($this->driver->ratings_count)->toBe(0)
        ->and($this->driver->hasRating())->toBeFalse();

    $this->getJson(route('api.rides.index'))->assertOk();
});

it('replaces her score rather than adding a second', function () {
    rateTrip($this->passenger, $this->booking, 2)->assertOk();

    expect($this->driver->refresh()->rating_average)->toBe('2.00')
        ->and($this->driver->ratings_count)->toBe(1);

    // She has one opinion of a trip and may change her mind.
    rateTrip($this->passenger, $this->booking, 5)
        ->assertOk()
        ->assertJsonPath('data.booking.rating', 5);

    expect($this->booking->refresh()->rating)->toBe(5)
        ->and($this->driver->refresh()->rating_average)->toBe('5.00')
        // Still one rating, not two.
        ->and($this->driver->ratings_count)->toBe(1);
});

it('refuses a trip that has not taken place yet', function () {
    $upcoming = Ride::factory()
        ->forDriver($this->driver)
        ->departingAt(Carbon::now()->addDay())
        ->create(['seats_offered' => 4]);

    $booking = Booking::factory()->for($upcoming)->for($this->passenger)->confirmed()->create();

    rateTrip($this->passenger, $booking, 5)
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors('rating');

    expect($booking->refresh()->rating)->toBeNull()
        ->and($this->driver->refresh()->ratings_count)->toBe(0);
});

it('refuses a trip the driver never confirmed her on', function (string $state) {
    $rider = User::factory()->passenger()->verified()->create();

    // `pending` is the factory default - the driver never answered at all.
    $factory = Booking::factory()->for($this->taken)->for($rider);
    $booking = ($state === 'pending' ? $factory : $factory->declined())->create();

    // Declined means she did not travel; pending means nobody ever said she
    // could. Neither is a trip to have an opinion about.
    rateTrip($booking->user, $booking, 5)
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors('rating');

    expect($booking->refresh()->rating)->toBeNull();
})->with(['declined', 'pending']);

it('refuses somebody else\'s trip without saying it exists', function () {
    $stranger = User::factory()->passenger()->verified()->create();

    rateTrip($stranger, $this->booking, 1)->assertNotFound();

    expect($this->booking->refresh()->rating)->toBeNull();
});

it('takes a whole number from one to five and nothing else', function (mixed $rating) {
    rateTrip($this->passenger, $this->booking, (int) $rating)
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors('rating');

    expect($this->booking->refresh()->rating)->toBeNull();
})->with([0, 6, -1]);

it('tells her on her own list whether a trip can be rated', function () {
    // A distinct label, because the assertion below keys on it and the
    // factory gives every ride the same default origin.
    $upcoming = Ride::factory()
        ->forDriver($this->driver)
        ->departingAt(Carbon::now()->addDay())
        ->create(['seats_offered' => 4, 'origin_label' => 'Still to come']);

    Booking::factory()->for($upcoming)->for($this->passenger)->confirmed()->create();

    $response = $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk();

    $bookings = collect($response->json('data.bookings'))->keyBy('origin_name');

    // The trip she has taken can be rated; the one still to come cannot.
    expect($bookings[$this->taken->origin_label]['can_rate'])->toBeTrue()
        ->and($bookings[$upcoming->origin_label]['can_rate'])->toBeFalse();
});

it('shows the driver\'s score on the public board, and still no driver', function () {
    rateTrip($this->passenger, $this->booking, 4)->assertOk();

    Ride::factory()
        ->forDriver($this->driver)
        ->departingAt(Carbon::now()->addDay())
        ->create(['seats_offered' => 4]);

    // No token: this is the one public read in the API.
    $this->getJson(route('api.rides.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.rides')
        ->assertJsonPath('data.rides.0.driver_rating.average', '4.00')
        ->assertJsonPath('data.rides.0.driver_rating.count', 1)
        // A score is an aggregate and identifies nobody. A name is a name.
        ->assertJsonMissingPath('data.rides.0.driver')
        ->assertJsonMissingPath('data.rides.0.user');
});

it('shows the driver\'s score beside his name where she can see him', function () {
    rateTrip($this->passenger, $this->booking, 5)->assertOk();

    $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonPath('data.bookings.0.driver.rating.average', '5.00')
        ->assertJsonPath('data.bookings.0.driver.rating.count', 1);

    $this->actingAs($this->passenger)
        ->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.driver.rating.average', '5.00');
});

it('gives a driver his own score', function () {
    rateTrip($this->passenger, $this->booking, 3)->assertOk();

    // The score was written to the driver's row, not to the stale instance
    // this test is holding.
    $this->actingAs($this->driver->refresh())
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertJsonPath('data.rating.average', '3.00')
        ->assertJsonPath('data.rating.count', 1);
});

it('recomputes a driver\'s score from the ratings themselves', function () {
    // The columns are a cache of this, never a thing to assign - the same
    // bargain the verification badge makes.
    // One booking per passenger per ride, so the two scores are two
    // passengers - which is what an average is over anyway.
    $this->booking->forceFill(['rating' => 5, 'rated_at' => now()])->save();

    $other = User::factory()->passenger()->verified()->create();
    Booking::factory()->for($this->taken)->for($other)->rated(2)->create();

    app(RatingService::class)->refreshFor($this->driver);

    expect($this->driver->refresh()->rating_average)->toBe('3.50')
        ->and($this->driver->ratings_count)->toBe(2);
});
