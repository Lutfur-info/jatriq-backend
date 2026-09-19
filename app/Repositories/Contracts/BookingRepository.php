<?php

namespace App\Repositories\Contracts;

use App\enum\BookingStatus;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Persistence for the seats passengers take on a ride.
 *
 * `ride_id` and `user_id` are arguments rather than attributes: this layer
 * owns both, so no request body can book seats in another passenger's name.
 */
interface BookingRepository
{
    /**
     * Every booking the passenger holds, upcoming trips first.
     *
     * All of them, past ones included - a booking is the record that a trip
     * was taken, not only that one is coming. Each carries its ride, that
     * ride's vehicle, and the ride's booked-seat total, so the list needs no
     * follow-up call.
     *
     * @return Collection<int, Booking>
     */
    public function forUser(User $user): Collection;

    /**
     * The passenger's booking on this ride, if they hold one.
     */
    public function forRideAndUser(Ride $ride, User $user): ?Booking;

    /**
     * Seats spoken for on the ride, across every passenger.
     *
     * Pending and confirmed alike: a request holds its seat until the driver
     * declines it, which is the only thing that gives one back.
     */
    public function seatsTakenOn(Ride $ride): int;

    /**
     * Whether anybody is holding a seat on the ride.
     *
     * This is what closes a ride to editing, so it asks about existence
     * rather than summing seats. A declined booking does not count - the
     * passenger is not travelling, so there is nobody whose terms an edit
     * would change.
     */
    public function anyOn(Ride $ride): bool;

    /**
     * Every booking on the ride, the declined ones included, newest last.
     *
     * The driver's queue. Declined requests stay in it because "I already
     * said no to this person" is the thing a driver most needs to see.
     * Carries each passenger, and the ride for the fare.
     *
     * @return Collection<int, Booking>
     */
    public function forRide(Ride $ride): Collection;

    /**
     * Record her score for the trip and stamp when she gave it.
     *
     * Replaces any score already there - she has one opinion of a trip and
     * may change her mind - and moves the stamp with it, because the later
     * score is the one that counts.
     *
     * The columns are this layer's to write: both are absent from the
     * model's fillable list, so no request body can post a score onto a
     * trip it did not take.
     */
    public function rate(Booking $booking, int $rating): Booking;

    /**
     * Record the driver's answer and stamp when it was given.
     *
     * The status is this layer's to write - it is absent from the model's
     * fillable list, so no request body can confirm its own request.
     */
    public function decide(Booking $booking, BookingStatus $status): Booking;

    /**
     * Ask for seats on the ride, topping up the booking already held.
     *
     * There is at most one row per passenger per ride, so a second booking
     * adds to the first rather than appending. Check
     * `Booking::$wasRecentlyCreated` to tell a new booking from a top-up.
     *
     * Every write here lands on **Pending** and clears any earlier decision:
     * the driver agreed to two seats and is now being asked for three, which
     * is a new question. A booking the driver **declined** starts over rather
     * than topping up - those seats were given back, so adding to them would
     * invent seats nobody is holding.
     */
    public function put(Ride $ride, User $user, int $seats): Booking;
}
