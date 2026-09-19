<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\TravelRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Repositories\Contracts\RideRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @phpstan-import-type RideAttributes from RideRepository
 * @phpstan-import-type RouteLeg from \App\Repositories\Contracts\TravelRouteRepository
 */
class RideEloquentRepository implements RideRepository
{
    /**
     * {@inheritDoc}
     */
    public function upcomingForUser(User $user): Collection
    {
        return $user->rides()
            ->with(['vehicle', 'travelRoute'])
            ->withSum(['bookings' => $this->holdingSeats(...)], 'seats')
            ->tap($this->withDriverRating(...))
            ->where('departs_at', '>=', now())
            ->orderBy('departs_at')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function available(?array $legs = null): Collection
    {
        // A journey was asked for and no corridor carries it, so no ride can.
        // Answered without a query - `whereIn` on an empty set would do the
        // same thing, but only by accident of how an empty OR group compiles.
        if ($legs === []) {
            return new Collection;
        }

        return $this->bookable($legs)->get();
    }

    /**
     * {@inheritDoc}
     */
    public function availablePage(?array $legs, int $perPage): LengthAwarePaginator
    {
        return $this->bookable($legs)->paginate($perPage)->withQueryString();
    }

    /**
     * The rides a passenger could still book, in the order they leave.
     *
     * The single statement of what "bookable" means, shared by the whole
     * collection the API returns and the page the web board renders - so
     * the two can never drift into answering different questions.
     *
     * @param  array<int, RouteLeg>|null  $legs
     * @return Builder<Ride>
     */
    private function bookable(?array $legs): Builder
    {
        $rides = Ride::query()
            ->with(['vehicle', 'travelRoute'])
            ->withSum(['bookings' => $this->holdingSeats(...)], 'seats')
            ->tap($this->withDriverRating(...))
            ->where('departs_at', '>=', now())
            /*
             * Not full. A correlated subquery rather than a HAVING on the
             * withSum alias, because that would need a GROUP BY over every
             * selected column - and this way it reads as the rule it is.
             *
             * A **pending** request holds its seat just as a confirmed one
             * does; only a decline gives one back. The two case names are
             * written out because `whereRaw` takes a literal - they are
             * `BookingStatus::holdingNames()`, and a new case has to be
             * added here by hand.
             */
            ->whereRaw(
                'rides.seats_offered > (select coalesce(sum(bookings.seats), 0) '
                .'from bookings where bookings.ride_id = rides.id '
                ."and bookings.status in ('Pending', 'Confirmed'))"
            );

        if ($legs === []) {
            /*
             * No corridor carries the journey, so nothing can match.
             * `available()` answers that without a query at all; a paginator
             * cannot, because it still has to count and still has to carry
             * the page links - so here the impossibility is written into the
             * query rather than asserted about an empty OR group.
             */
            $rides->whereRaw('1 = 0');
        } elseif ($legs !== null) {
            $rides->where(fn (Builder $query) => $this->coveringAnyLeg($query, $legs));
        }

        return $rides->orderBy('departs_at');
    }

    /**
     * Restrict to the rides that carry the passenger over one of these legs.
     *
     * Each leg is one corridor's reading of the journey, so they are ORed:
     * Cumilla to Dhaka is a leg of three different corridors, and a ride
     * coming down any of them serves it.
     *
     * Within a leg the rule is **containment**. The ride's span has to swallow
     * the passenger's, travelling the same way:
     *
     *     Ride    Sonaimuri (70) ----------------------------> Dhaka (10)
     *     Wanted            Laksam (60) -------------------> Dhaka (10)
     *
     * Sequence runs outbound from Dhaka on every corridor, so a leg whose
     * `to_sequence` is the lower number is heading toward Dhaka, and its
     * comparisons flip. A ride running the other way fails the first of them
     * and is never returned - which is what stops a Dhaka-bound search
     * offering seats on a vehicle leaving Dhaka.
     *
     * @param  Builder<Ride>  $query
     * @param  array<int, RouteLeg>  $legs
     */
    private function coveringAnyLeg(Builder $query, array $legs): void
    {
        foreach ($legs as $leg) {
            $query->orWhere(function (Builder $onRoute) use ($leg): void {
                $onRoute->where('travel_route_id', $leg['travel_route_id']);

                $towardDhaka = $leg['to_sequence'] < $leg['from_sequence'];

                if ($towardDhaka) {
                    $onRoute
                        // Boarded at or after where the ride starts...
                        ->where('origin_sequence', '>=', $leg['from_sequence'])
                        // ...and set down at or before where it ends.
                        ->where('destination_sequence', '<=', $leg['to_sequence']);

                    return;
                }

                $onRoute
                    ->where('origin_sequence', '<=', $leg['from_sequence'])
                    ->where('destination_sequence', '>=', $leg['to_sequence']);
            });
        }
    }

    /**
     * Carry the driver's score onto the ride without carrying the driver.
     *
     * Two correlated subselects rather than `with('user')`. That is not a
     * micro-optimisation: `GET /api/rides` is **public**, and the rule that
     * it must never carry anything about the driver is easiest to keep when
     * there is no `User` on the ride to be tempted by. A rating is an
     * aggregate and identifies nobody; a name and a number do.
     *
     * Null average where nobody has rated him, which is not zero.
     *
     * @param  Builder<Ride>  $rides
     */
    private function withDriverRating(Builder $rides): void
    {
        $rides->addSelect([
            'rides.*',
            'driver_rating_average' => User::query()
                ->select('rating_average')
                ->whereColumn('users.id', 'rides.user_id'),
            'driver_ratings_count' => User::query()
                ->select('ratings_count')
                ->whereColumn('users.id', 'rides.user_id'),
        ]);
    }

    /**
     * The bookings a seat sum counts: everything but a declined one.
     *
     * A pending request holds its seat exactly as a confirmed one does, so
     * every `withSum` on this class runs through here rather than summing
     * the raw column - a ride whose sum included declines would tell a
     * passenger the car is fuller than it is.
     *
     * @param  Builder<Booking>  $bookings
     * @return Builder<Booking>
     */
    private function holdingSeats(Builder $bookings): Builder
    {
        return $bookings->holding();
    }

    /**
     * {@inheritDoc}
     */
    public function create(User $user, Vehicle $vehicle, array $attributes): Ride
    {
        $ride = new Ride;

        $ride->fill($attributes);
        $this->place($ride, $attributes);
        $ride->user()->associate($user);
        $ride->vehicle()->associate($vehicle);
        $ride->save();

        // The resource renders the car alongside the ride, and the vehicle is
        // already in hand - so hand it over rather than reading it back. The
        // corridor was resolved to an id, not a model, so that one is read.
        $ride->setRelation('vehicle', $vehicle);
        $ride->load('travelRoute');

        return $ride;
    }

    /**
     * {@inheritDoc}
     */
    public function update(Ride $ride, array $attributes): Ride
    {
        $ride->fill($attributes);
        $this->place($ride, $attributes);
        $ride->save();

        $ride->loadMissing('vehicle');

        // An edit can move the ride onto a different corridor, so this is a
        // refresh rather than a loadMissing.
        $ride->load('travelRoute');

        return $ride;
    }

    /**
     * Put the ride on its corridor.
     *
     * These five are guarded for the same reason `user_id` and `vehicle_id`
     * are, so `fill()` will not write them: the corridor and the two
     * sequences are RideService's conclusion about which road the driver's
     * two stops share, never something a request body gets to assert.
     *
     * Only the keys present are written, so a partial edit that moves neither
     * end leaves the placement alone.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function place(Ride $ride, array $attributes): void
    {
        $columns = [
            'travel_route_id',
            'origin_stop_id',
            'origin_sequence',
            'destination_stop_id',
            'destination_sequence',
        ];

        foreach ($columns as $column) {
            if (array_key_exists($column, $attributes)) {
                $ride->setAttribute($column, $attributes[$column]);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findUpcoming(int $rideId): ?Ride
    {
        return Ride::query()
            ->with(['vehicle', 'travelRoute'])
            ->withSum(['bookings' => $this->holdingSeats(...)], 'seats')
            ->tap($this->withDriverRating(...))
            ->whereKey($rideId)
            ->where('departs_at', '>=', now())
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function lockForWrite(Ride $ride): Ride
    {
        return Ride::query()
            ->whereKey($ride->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * {@inheritDoc}
     */
    public function withBookedSeats(Ride $ride): Ride
    {
        return Ride::query()
            ->with(['vehicle', 'travelRoute'])
            ->withSum(['bookings' => $this->holdingSeats(...)], 'seats')
            ->tap($this->withDriverRating(...))
            ->whereKey($ride->getKey())
            ->firstOrFail();
    }

    /**
     * {@inheritDoc}
     */
    public function resequenceOnCorridor(TravelRoute $route, array $sequenceByStopId): int
    {
        $rewritten = 0;

        Ride::query()
            ->where('travel_route_id', $route->getKey())
            ->whereNotNull('origin_stop_id')
            ->whereNotNull('destination_stop_id')
            ->each(function (Ride $ride) use ($sequenceByStopId, &$rewritten): void {
                $origin = $sequenceByStopId[$ride->origin_stop_id] ?? null;
                $destination = $sequenceByStopId[$ride->destination_stop_id] ?? null;

                // A ride whose stop has since come off the corridor has no
                // new position to move to. Left as it is rather than guessed
                // at - it is already unsearchable, and inventing a sequence
                // would put it back on the road in the wrong place.
                if ($origin === null || $destination === null) {
                    return;
                }

                $ride->forceFill([
                    'origin_sequence' => $origin,
                    'destination_sequence' => $destination,
                ])->save();

                $rewritten++;
            });

        return $rewritten;
    }
}
