<?php

namespace App\Models;

use App\enum\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A passenger's request for seats on a ride, and the driver's answer.
 *
 * A seat is asked for, not taken: the driver is letting a stranger into their
 * car, so every booking arrives `Pending` and only they can move it. A
 * **pending booking holds its seats** exactly as a confirmed one does -
 * declining is the only thing that frees a seat - so the whole system's seat
 * arithmetic reads `BookingStatus::holdingNames()` and never the raw sum.
 *
 * There is at most one booking per passenger per ride: booking again tops up
 * the seats on this row. `seats` is therefore a running total, not a single
 * act of booking - and a top-up puts the row back to `Pending`, because the
 * driver agreed to two seats and is now being asked for three.
 *
 * Her score for the trip lives here too, and for the same reason the
 * driver's answer does: the booking already *is* the one row per passenger
 * per ride, so `unique(ride_id, user_id)` gives "one rating per trip" for
 * free. `status` is his answer to her request; `rating` is hers to the trip.
 *
 * The fare is deliberately **not** snapshotted here. A ride cannot be edited
 * once it has a booking, so `ride.seat_price` can never move underneath one -
 * the price agreed is always the ride's own, and a copy would only be
 * something to keep in step.
 *
 * `ride_id` and `user_id` are absent from the fillable list, and so are
 * `status`, `decided_at`, `rating` and `rated_at`: the repository associates
 * the first two and records both answers itself, so no request body can book
 * seats in somebody else's name, confirm its own request, or post a score
 * onto a trip it did not take.
 *
 * @property int $id
 * @property int $ride_id
 * @property int $user_id
 * @property int $seats
 * @property BookingStatus $status
 * @property Carbon|null $decided_at
 * @property int|null $rating
 * @property Carbon|null $rated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ride $ride
 * @property-read User $user
 */
#[Fillable(['seats'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'status' => BookingStatus::class,
            'decided_at' => 'datetime',
            'rating' => 'integer',
            'rated_at' => 'datetime',
        ];
    }

    /**
     * What the passenger owes for these seats, in BDT.
     *
     * Fixed point throughout - `bcmul` on the ride's own `seat_price`, never
     * a float multiplication - so a fare of 650.50 times two is exactly
     * 1301.00 and not 1300.9999999999998.
     *
     * Derived rather than stored, and safely so: a ride with a booking can
     * no longer change its price, so this can never drift from what was
     * agreed. Reads `ride`, so eager load it when rendering a list.
     */
    public function totalAmount(): string
    {
        return bcmul($this->ride->seat_price, (string) $this->seats, 2);
    }

    /**
     * Whether these seats are off the market.
     *
     * Pending and Confirmed both hold; only a decline gives a seat back.
     */
    public function holdsSeats(): bool
    {
        return $this->status->holdsSeats();
    }

    /**
     * Whether she may score this trip.
     *
     * Two conditions, and neither is a ride status - there is no ride
     * lifecycle in this product, no "complete" to press and nothing to
     * press it.
     *
     * **The ride has departed**, because a trip is rated once taken and
     * `departs_at < now()` is the only record that it happened. And **the
     * driver confirmed her seat**: a request he declined, or never answered,
     * means she did not travel and so has no trip to score.
     *
     * Deliberately not "and she has not rated it yet" - re-rating replaces
     * the score, so this answers whether she may write, not whether the
     * field is empty.
     */
    public function isRateable(): bool
    {
        return $this->status === BookingStatus::Confirmed
            && $this->ride->departs_at->isPast();
    }

    /**
     * Limit a query to the bookings whose seats are spoken for.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    #[Scope]
    protected function holding(Builder $query): Builder
    {
        return $query->whereIn('status', BookingStatus::holdingNames());
    }

    /**
     * Limit a query to the bookings carrying a score.
     *
     * The single statement of what a driver's average is taken over: a
     * confirmed trip that somebody actually scored. A pending or declined
     * booking can never have one, but saying so here means the aggregate
     * does not depend on that staying true.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    #[Scope]
    protected function rated(Builder $query): Builder
    {
        return $query->whereNotNull('rating');
    }

    /**
     * The ride the seats are on.
     *
     * @return BelongsTo<Ride, $this>
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * The passenger who booked.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
