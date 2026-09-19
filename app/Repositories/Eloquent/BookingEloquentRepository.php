<?php

namespace App\Repositories\Eloquent;

use App\enum\BookingStatus;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use App\Repositories\Contracts\BookingRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class BookingEloquentRepository implements BookingRepository
{
    /**
     * {@inheritDoc}
     */
    public function forUser(User $user): Collection
    {
        $now = now()->toDateTimeString();

        return $user->bookings()
            /*
             * BookingResource reads the ride's labels, departure and vehicle
             * number, so those come along rather than one query per booking.
             * The driver does too - a name and a badge, which is how she
             * knows who drove and what she is favouriting.
             */
            ->with(['ride.vehicle', 'ride.user'])
            // Ordering is by the ride's departure, so the ride has to be
            // joined; `select` keeps `bookings.*` from colliding with it.
            ->join('rides', 'rides.id', '=', 'bookings.ride_id')
            ->select('bookings.*')
            /*
             * Upcoming trips first, soonest at the top, then the trips
             * already taken, most recent at the top. The second key is null
             * for past rides, so they all tie there and fall through to the
             * third; upcoming rides never reach it.
             */
            ->orderByRaw('CASE WHEN rides.departs_at >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN rides.departs_at >= ? THEN rides.departs_at END asc', [$now])
            ->orderBy('rides.departs_at', 'desc')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function forRideAndUser(Ride $ride, User $user): ?Booking
    {
        return $ride->bookings()
            ->where('user_id', $user->getKey())
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function seatsTakenOn(Ride $ride): int
    {
        return (int) $ride->bookings()->holding()->sum('seats');
    }

    /**
     * {@inheritDoc}
     */
    public function anyOn(Ride $ride): bool
    {
        return $ride->bookings()->holding()->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function forRide(Ride $ride): Collection
    {
        // BookingResource prints the plate off the ride, and every row here
        // is on the same one - so load it once rather than per booking.
        $ride->loadMissing('vehicle');

        return $ride->bookings()
            ->with('user')
            // Waiting first - the queue is what the driver came for - then
            // the answered ones, most recently asked at the top.
            ->orderByRaw("case when status = 'Pending' then 0 else 1 end")
            ->orderBy('created_at', 'desc')
            ->get()
            ->each(fn (Booking $booking) => $booking->setRelation('ride', $ride));
    }

    /**
     * {@inheritDoc}
     */
    public function rate(Booking $booking, int $rating): Booking
    {
        $booking->rating = $rating;
        $booking->rated_at = Carbon::now();
        $booking->save();

        // RatingService refreshes the driver off this, and the caller renders
        // the fare off the ride, so both come along rather than being read
        // back twice.
        $booking->loadMissing('ride.user', 'ride.vehicle');

        return $booking;
    }

    /**
     * {@inheritDoc}
     */
    public function decide(Booking $booking, BookingStatus $status): Booking
    {
        $booking->status = $status;
        $booking->decided_at = Carbon::now();
        $booking->save();

        return $booking;
    }

    /**
     * {@inheritDoc}
     */
    public function put(Ride $ride, User $user, int $seats): Booking
    {
        $booking = $this->forRideAndUser($ride, $user);

        if ($booking === null) {
            $booking = new Booking(['seats' => $seats]);
            $booking->ride()->associate($ride);
            $booking->user()->associate($user);
            $booking->status = BookingStatus::Pending;
            $booking->save();
        } else {
            /*
             * A second request tops up the same row - unless the driver
             * declined the first. Those seats went back on the market, so
             * adding to them would invent seats nobody holds; asking again
             * after a decline starts from nothing.
             */
            $booking->seats = $booking->holdsSeats()
                ? $booking->seats + $seats
                : $seats;

            // Back to the driver either way: they agreed to two seats and
            // are now being asked for three, which is a new question.
            $booking->status = BookingStatus::Pending;
            $booking->decided_at = null;
            $booking->save();
        }

        // The resource needs the ride and its vehicle for the fare and the
        // plate; the ride is already in hand, so hand it over rather than
        // reading it back.
        $ride->loadMissing('vehicle');
        $booking->setRelation('ride', $ride);

        return $booking;
    }
}
