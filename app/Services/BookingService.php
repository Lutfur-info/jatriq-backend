<?php

namespace App\Services;

use App\enum\BookingStatus;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use App\Notifications\BookingDecided;
use App\Repositories\Contracts\BookingRepository;
use App\Repositories\Contracts\RideRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seats asked for on somebody else's ride, and the driver's answer.
 *
 * A passenger supplies a seat count and nothing else: the route, the
 * departure and the fare are the driver's, and are fixed from the moment the
 * first seat is asked for - see RideService::change().
 *
 * The seat is **requested**, not taken. A driver is letting a stranger into
 * their car, so every booking arrives Pending and the driver confirms or
 * declines it. A pending request holds its seats throughout, which is what
 * keeps a driver from confirming more seats than the car has; declining is
 * the only thing that gives one back.
 */
class BookingService
{
    public function __construct(
        private BookingRepository $bookings,
        private RideRepository $rides,
    ) {}

    /**
     * Every booking the passenger holds, upcoming trips first.
     *
     * @return Collection<int, Booking>
     */
    public function listFor(User $user): Collection
    {
        return $this->bookings->forUser($user);
    }

    /**
     * Take seats on a ride for the passenger.
     *
     * Booking again tops up the booking they already hold rather than adding
     * a second one; `Booking::$wasRecentlyCreated` tells the two apart.
     *
     * The whole thing runs inside a transaction with the ride row locked,
     * because the seats free have to be counted and then spent as one step -
     * otherwise two passengers reaching for the last seat both read "one
     * free" and both succeed. Nothing is written unless every check passed:
     * a refusal throws, which rolls the transaction back untouched.
     *
     * The three retries are for the lock itself, not for the rules. Laravel
     * re-runs the closure only on a concurrency error - a deadlock or a lock
     * wait timeout - so a passenger who queued too long behind somebody
     * else's booking tries again instead of seeing a 500. A ValidationException
     * is not a concurrency error and is never retried.
     *
     * @throws ValidationException when the ride has left, or when the seats
     *                             asked for are not there.
     */
    public function book(Ride $ride, User $user, int $seats): Booking
    {
        return DB::transaction(function () use ($ride, $user, $seats): Booking {
            $locked = $this->rides->lockForWrite($ride);

            if ($locked->departs_at->isPast()) {
                throw ValidationException::withMessages([
                    'ride' => 'This ride has already left.',
                ]);
            }

            $taken = $this->bookings->seatsTakenOn($locked);
            $free = $locked->seats_offered - $taken;

            if ($seats > $free) {
                throw ValidationException::withMessages([
                    'seats' => $free === 0
                        ? 'This ride is full.'
                        : "Only {$free} of the seats on this ride are still free.",
                ]);
            }

            return $this->bookings->put($locked, $user, $seats);
        }, attempts: 3);
    }

    /**
     * Every request on the driver's ride, waiting ones first.
     *
     * @return Collection<int, Booking>
     */
    public function listForRide(Ride $ride): Collection
    {
        return $this->bookings->forRide($ride);
    }

    /**
     * Record the driver's answer to a request.
     *
     * Confirming is not a formality, because declining gives a seat back:
     * between a decline and a change of mind somebody else may have taken
     * it. So the seats are counted again, under the same row lock a booking
     * takes, and a confirmation that no longer fits is refused rather than
     * overselling the car. Confirming a request that is already holding its
     * seats needs no room - it is already counted.
     *
     * Deciding twice is allowed and is not a no-op: a driver who confirmed
     * by mistake can still decline, and the stamp moves to the later answer.
     * Sending the answer a booking already has is accepted quietly, so a
     * client retrying a lost response does not get an error.
     *
     * **The passenger is told.** She has been waiting on exactly this since
     * she asked, and she will not have the app open when it lands. Two
     * things about where that happens:
     *
     * It is sent from here rather than from the controller, so every route
     * to a decision carries it - an admin override, a console command, a
     * future auto-decline on departure - and it is sent **after** the
     * transaction has committed, so a rolled-back decision cannot leave a
     * notification claiming it happened.
     *
     * And only when the answer actually changed. Re-sending the answer a
     * booking already has is exactly what a client with a lost response
     * does, and telling her twice that her seat was confirmed would make
     * the feed a worse record than the booking itself.
     *
     * @throws ValidationException when confirming no longer fits in the car.
     */
    public function decide(Booking $booking, BookingStatus $status): Booking
    {
        $was = $booking->status;

        $decided = DB::transaction(function () use ($booking, $status): Booking {
            $ride = $this->rides->lockForWrite($booking->ride);

            if ($status === BookingStatus::Confirmed && ! $booking->holdsSeats()) {
                /*
                 * These seats were given back when the request was declined,
                 * so taking them again has to fit in what is free now.
                 */
                $free = $ride->seats_offered - $this->bookings->seatsTakenOn($ride);

                if ($booking->seats > $free) {
                    throw ValidationException::withMessages([
                        'booking' => $free === 0
                            ? 'There is no seat left to confirm - the ride filled up after you declined this request.'
                            : "Only {$free} of the seats are still free, and this request is for {$booking->seats}.",
                    ]);
                }
            }

            $decided = $this->bookings->decide($booking, $status);

            // The caller renders the fare off the ride, and the locked row
            // is the one in hand.
            $decided->setRelation('ride', $ride);

            return $decided;
        }, attempts: 3);

        if ($was !== $status) {
            $decided->user->notify(new BookingDecided($decided));
        }

        return $decided;
    }

    /**
     * The ride as it stands after a booking, ready to render.
     */
    public function rideAfterBooking(Ride $ride): Ride
    {
        return $this->rides->withBookedSeats($ride);
    }
}
