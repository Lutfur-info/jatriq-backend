<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Repositories\Contracts\BookingRepository;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What a passenger thought of a trip she took, and what that adds up to for
 * the driver.
 *
 * The driver's score is **derived, never assigned** - exactly as the
 * verification badge is. `users.rating_average` and `users.ratings_count` are
 * a cache of `refreshFor()`'s answer, recomputed from the ratings themselves
 * after every write, so nothing that writes a score may skip the refresh.
 * They are denormalised only because the public ride board shows a rating on
 * every card and cannot average a join per row.
 *
 * There is **no ride lifecycle** in this product - no "complete" to press -
 * so "she took the trip" is read off what already exists: the driver
 * confirmed her seat, and the ride has departed. See `Booking::isRateable()`.
 */
class RatingService
{
    public function __construct(
        private BookingRepository $bookings,
        private UserRepository $users,
    ) {}

    /**
     * Score a trip out of five.
     *
     * Rating again **replaces** the score rather than adding a second: the
     * booking is the one row per passenger per trip, so a passenger has one
     * opinion of it and is allowed to change her mind.
     *
     * The write and the driver's refreshed average are one transaction. A
     * score recorded without the average moving would leave every ride card
     * in the country quoting a number that no longer matches its own
     * ratings, and nothing would ever go back and notice.
     *
     * @throws ValidationException when the trip is not hers to score yet.
     */
    public function rate(Booking $booking, int $rating): Booking
    {
        $this->refuseUnfinished($booking);

        return DB::transaction(function () use ($booking, $rating): Booking {
            $rated = $this->bookings->rate($booking, $rating);

            $this->refreshFor($rated->ride->user);

            return $rated;
        });
    }

    /**
     * Recompute the driver's score from the trips actually rated.
     *
     * The single place either column is written. Reads through
     * `User::receivedRatings()`, which reaches driver -> rides -> bookings,
     * so only somebody who travelled on one of his rides can move it.
     *
     * A driver with no ratings gets a **null** average rather than zero:
     * "nobody has rated him" and "he is rated nought" are different claims
     * and must never render alike.
     */
    public function refreshFor(User $driver): User
    {
        $ratings = $driver->receivedRatings();

        $count = (clone $ratings)->count();
        $average = $count === 0 ? null : (clone $ratings)->avg('bookings.rating');

        return $this->users->putRating(
            $driver,
            $average === null ? null : round((float) $average, 2),
            $count,
        );
    }

    /**
     * Refuse a trip she has not taken.
     *
     * A 422 keyed on the field, like every other refusal in this API. Two
     * different refusals rather than one, because "wait until you have
     * travelled" and "the driver never confirmed your seat" are different
     * problems and only one of them resolves itself.
     *
     * @throws ValidationException
     */
    private function refuseUnfinished(Booking $booking): void
    {
        $booking->loadMissing('ride');

        if (! $booking->holdsSeats() || $booking->status->name === 'Pending') {
            throw ValidationException::withMessages([
                'rating' => 'You can only rate a trip the driver confirmed you on.',
            ]);
        }

        if ($booking->ride->departs_at->isFuture()) {
            throw ValidationException::withMessages([
                'rating' => 'You can rate this trip once it has taken place.',
            ]);
        }
    }
}
