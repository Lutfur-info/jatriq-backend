<?php

use App\enum\BookingStatus;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

/*
 * A seat is asked for, not taken. The driver is letting a stranger into their
 * car, so every booking arrives Pending and waits for their answer.
 *
 * The rule the whole feature turns on: a **pending request holds its seats**
 * exactly as a confirmed one does, and declining is the only thing that gives
 * one back. Otherwise a driver could confirm more seats than the car has and
 * the oversell would just move from the booking to the confirmation.
 */
beforeEach(function () {
    $this->driver = User::factory()->driver()->verified()->create();
    $this->passenger = User::factory()->passenger()->verified()->create();

    $this->ride = Ride::factory()->forDriver($this->driver)->create(['seats_offered' => 4]);
});

/**
 * The passenger asks for seats, the way the API does.
 */
function request_seats(User $passenger, Ride $ride, int $seats = 1): Booking
{
    test()->actingAs($passenger)
        ->postJson(route('api.rides.bookings.store', ['ride' => $ride]), ['seats' => $seats])
        ->assertStatus(Response::HTTP_CREATED);

    return Booking::query()->where('ride_id', $ride->id)->where('user_id', $passenger->id)->sole();
}

it('parks a new request on the driver rather than taking the seat outright', function () {
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', ['ride' => $this->ride]), ['seats' => 2])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('data.booking.status', 'Pending')
        ->assertJsonPath('data.booking.status_label', 'Awaiting the driver')
        ->assertJsonPath('data.booking.decided_at', null)
        // The seats are held from the moment they are asked for, so nobody
        // else is offered them while the driver thinks about it.
        ->assertJsonPath('data.ride.seats_booked', 2)
        ->assertJsonPath('data.ride.seats_available', 2);
});

it('shows the driver who is waiting, without handing over a phone number', function () {
    $booking = request_seats($this->passenger, $this->ride, 2);

    $this->actingAs($this->driver)
        ->getJson(route('api.driver.rides.bookings.index', ['ride' => $this->ride]))
        ->assertOk()
        ->assertJsonPath('message', 'Requests for seats on this ride.')
        ->assertJsonCount(1, 'data.bookings')
        ->assertJsonPath('data.bookings.0.id', $booking->id)
        ->assertJsonPath('data.bookings.0.status', 'Pending')
        ->assertJsonPath('data.bookings.0.seats', 2)
        ->assertJsonPath('data.bookings.0.passenger.name', $this->passenger->full_name)
        ->assertJsonPath('data.bookings.0.passenger.verification_status', 'Verified')
        // RiderResource carries no contact details, in either direction.
        ->assertJsonMissingPath('data.bookings.0.passenger.msisdn')
        ->assertJsonMissingPath('data.bookings.0.passenger.email');
});

it('answers an empty queue before anybody asks', function () {
    $this->actingAs($this->driver)
        ->getJson(route('api.driver.rides.bookings.index', ['ride' => $this->ride]))
        ->assertOk()
        ->assertJsonPath('message', 'Nobody has asked for a seat yet.')
        ->assertJsonCount(0, 'data.bookings');
});

it('confirms a seat', function () {
    $booking = request_seats($this->passenger, $this->ride, 2);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), [
            'status' => 'Confirmed',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Seat confirmed.')
        ->assertJsonPath('data.booking.status', 'Confirmed');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->decided_at)->not->toBeNull()
        // Confirming changes nothing about the seats: they were already held.
        ->and($booking->seats)->toBe(2);
});

it('puts the seats back on sale when the driver declines', function () {
    $booking = request_seats($this->passenger, $this->ride, 3);

    // Held while pending, so only one is on offer.
    $this->getJson(route('api.rides.index'))
        ->assertOk()
        ->assertJsonPath('data.rides.0.seats_available', 1);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), [
            'status' => 'Declined',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Request declined, and the seats are back on sale.')
        ->assertJsonPath('data.booking.status', 'Declined');

    // A decline is the only thing that gives a seat back.
    $this->getJson(route('api.rides.index'))
        ->assertOk()
        ->assertJsonPath('data.rides.0.seats_booked', 0)
        ->assertJsonPath('data.rides.0.seats_available', 4);
});

it('refuses to answer somebody else\'s ride, and will not say it exists', function () {
    $booking = request_seats($this->passenger, $this->ride);

    $stranger = User::factory()->driver()->verified()->create();

    $this->actingAs($stranger)
        ->getJson(route('api.driver.rides.bookings.index', ['ride' => $this->ride]))
        ->assertNotFound();

    $this->actingAs($stranger)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), [
            'status' => 'Confirmed',
        ])
        ->assertNotFound();

    expect($booking->refresh()->status)->toBe(BookingStatus::Pending);
});

it('keeps a passenger out of the driver\'s queue entirely', function () {
    $booking = request_seats($this->passenger, $this->ride);

    // `role:Driver`, so she cannot confirm her own request.
    $this->actingAs($this->passenger)
        ->getJson(route('api.driver.rides.bookings.index', ['ride' => $this->ride]))
        ->assertStatus(Response::HTTP_FORBIDDEN);

    $this->actingAs($this->passenger)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), [
            'status' => 'Confirmed',
        ])
        ->assertStatus(Response::HTTP_FORBIDDEN);

    expect($booking->refresh()->status)->toBe(BookingStatus::Pending);
});

it('lets an unverified driver read the queue but not answer it', function () {
    $unverified = User::factory()->driver()->create();
    $ride = Ride::factory()->forDriver($unverified)->create(['seats_offered' => 4]);
    $booking = request_seats($this->passenger, $ride);

    // The same bargain GET /driver/rides makes: a lapsed badge still sees
    // what is out there.
    $this->actingAs($unverified)
        ->getJson(route('api.driver.rides.bookings.index', ['ride' => $ride]))
        ->assertOk()
        ->assertJsonCount(1, 'data.bookings');

    // Answering is what actually puts a stranger in the car.
    $this->actingAs($unverified)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), [
            'status' => 'Confirmed',
        ])
        ->assertStatus(Response::HTTP_FORBIDDEN)
        ->assertJsonPath('data.verification_status', 'Unverified');

    expect($booking->refresh()->status)->toBe(BookingStatus::Pending);
});

it('takes Confirmed or Declined and nothing else', function (string $status) {
    $booking = request_seats($this->passenger, $this->ride);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), [
            'status' => $status,
        ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors('status');

    expect($booking->refresh()->status)->toBe(BookingStatus::Pending);
    // Pending is where a request arrives; only the passenger asking again
    // puts one back, so a driver cannot un-answer one.
})->with(['Pending', 'Cancelled', '']);

it('re-confirms a declined request while the seat is still there', function () {
    $booking = request_seats($this->passenger, $this->ride, 2);

    $this->actingAs($this->driver);

    $this->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), ['status' => 'Declined'])
        ->assertOk();

    // A driver who said no by mistake can still say yes.
    $this->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), ['status' => 'Confirmed'])
        ->assertOk()
        ->assertJsonPath('data.booking.status', 'Confirmed');

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('refuses to re-confirm a declined request into a car that has filled up', function () {
    $booking = request_seats($this->passenger, $this->ride, 3);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), ['status' => 'Declined'])
        ->assertOk();

    // Those three seats went back on sale and somebody else took them.
    request_seats(User::factory()->passenger()->verified()->create(), $this->ride, 4);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), ['status' => 'Confirmed'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors('booking');

    // Refused rather than overselling the car.
    expect($booking->refresh()->status)->toBe(BookingStatus::Declined)
        ->and($this->ride->bookings()->holding()->sum('seats'))->toBe(4);
});

it('sends a confirmed booking back to the driver when the passenger asks for more', function () {
    $booking = request_seats($this->passenger, $this->ride, 1);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), ['status' => 'Confirmed'])
        ->assertOk();

    // The driver agreed to one seat and is now being asked for two, which is
    // a new question - so the whole row goes back into the queue.
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', ['ride' => $this->ride]), ['seats' => 1])
        ->assertOk()
        ->assertJsonPath('data.booking.status', 'Pending')
        ->assertJsonPath('data.booking.seats', 2)
        ->assertJsonPath('data.booking.decided_at', null);

    expect($booking->refresh()->status)->toBe(BookingStatus::Pending)
        ->and($booking->seats)->toBe(2)
        ->and($booking->decided_at)->toBeNull();
});

it('starts over rather than topping up after a decline', function () {
    $booking = request_seats($this->passenger, $this->ride, 3);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), ['status' => 'Declined'])
        ->assertOk();

    // Those three seats were given back, so asking for one now means one -
    // topping up would invent seats nobody is holding.
    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', ['ride' => $this->ride]), ['seats' => 1])
        ->assertOk()
        ->assertJsonPath('data.booking.seats', 1)
        ->assertJsonPath('data.booking.status', 'Pending')
        ->assertJsonPath('data.ride.seats_available', 3);

    expect($booking->refresh()->seats)->toBe(1);
});

it('closes the ride to editing on a request nobody has answered yet', function () {
    request_seats($this->passenger, $this->ride);

    // The passenger asked for that route, that departure and that fare - the
    // driver answering is not what settles the terms.
    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', ['ride' => $this->ride]), ['seat_price' => 999])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors('ride');
});

it('reopens the ride to editing once every request has been declined', function () {
    $booking = request_seats($this->passenger, $this->ride);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), ['status' => 'Declined'])
        ->assertOk();

    // Nobody is travelling, so there are no terms left to protect.
    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.rides.update', ['ride' => $this->ride]), ['seat_price' => 999])
        ->assertOk();

    expect($this->ride->refresh()->seat_price)->toBe('999.00');
});

it('tells the passenger where her request stands', function () {
    $booking = request_seats($this->passenger, $this->ride, 2);

    $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonPath('data.bookings.0.status', 'Pending')
        ->assertJsonPath('data.bookings.0.status_label', 'Awaiting the driver')
        // She is reading her own list, so there is nobody to name.
        ->assertJsonMissingPath('data.bookings.0.passenger');

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), ['status' => 'Confirmed'])
        ->assertOk();

    $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonPath('data.bookings.0.status', 'Confirmed')
        ->assertJsonPath('data.bookings.0.status_label', 'Confirmed');
});
