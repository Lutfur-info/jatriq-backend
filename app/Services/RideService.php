<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\User;
use App\Repositories\Contracts\BookingRepository;
use App\Repositories\Contracts\RideRepository;
use App\Repositories\Contracts\VehicleRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The rides a driver offers.
 *
 * A ride is always in a vehicle, so the vehicle is resolved here rather than
 * accepted from the request: the seats being sold are that car's passenger
 * seats, and a driver who has not registered one has nothing to sell. The
 * corridor is resolved the same way and for the same reason.
 *
 * `RideDetails` is what a driver actually sends - the two stops and the terms.
 * It becomes the wider `RideAttributes` the repository writes only once the
 * placement has been worked out, which is the whole difference between a
 * claim and a fact here.
 *
 * @phpstan-type RideDetails array{
 *     origin_stop_id: int,
 *     destination_stop_id: int,
 *     departs_at: Carbon,
 *     seat_price: string,
 *     seats_offered: int,
 * }
 *
 * @phpstan-import-type RideAttributes from RideRepository
 * @phpstan-import-type RouteLeg from \App\Repositories\Contracts\TravelRouteRepository
 */
class RideService
{
    public function __construct(
        private RideRepository $rides,
        private VehicleRepository $vehicles,
        private BookingRepository $bookings,
        private TravelRouteService $routes,
    ) {}

    /**
     * The driver's rides that have not left yet, soonest first.
     *
     * @return Collection<int, Ride>
     */
    public function upcomingFor(User $user): Collection
    {
        return $this->rides->upcomingForUser($user);
    }

    /**
     * Rides a passenger could still book, soonest first.
     *
     * With no journey named this is the whole shop window, as it always was.
     * Name both ends and it becomes the search the product is built around: a
     * ride serves a passenger when it runs the same corridor, the same way,
     * and its own trip *contains* theirs. So somebody boarding at Laksam for
     * Dhaka is shown the vehicle that set out from Sonaimuri, because it has
     * Laksam still ahead of it.
     *
     * Naming one end alone is meaningless - "from Laksam" does not say which
     * way - so the request insists on both or neither.
     *
     * @return Collection<int, Ride>
     */
    public function available(?int $fromStopId = null, ?int $toStopId = null): Collection
    {
        return $this->rides->available($this->journey($fromStopId, $toStopId));
    }

    /**
     * The same rides, one page at a time, for the public web board.
     *
     * Same search, same rules, same order - a browser simply cannot be handed
     * every bookable ride at once the way the mobile clients are. Paging the
     * JSON list instead would change a response shape those clients already
     * parse, so the two reads sit side by side.
     *
     * @return LengthAwarePaginator<int, Ride>
     */
    public function availablePage(?int $fromStopId, ?int $toStopId, int $perPage): LengthAwarePaginator
    {
        return $this->rides->availablePage($this->journey($fromStopId, $toStopId), $perPage);
    }

    /**
     * One upcoming ride, or null if it has left or never existed.
     *
     * The detail page behind every card on the board. A ride whose seats are
     * all taken is still returned - it has dropped off the board, but
     * somebody holding the link is owed "fully booked" rather than a 404.
     */
    public function findUpcoming(int $rideId): ?Ride
    {
        return $this->rides->findUpcoming($rideId);
    }

    /**
     * The passenger's journey as corridors, or null if they named none.
     *
     * The one place the two empty answers are told apart: **null** is "no
     * journey was asked for", and an empty array is "a journey was asked for
     * and no corridor carries it". Collapsing them turns a hopeless search
     * into an unfiltered list.
     *
     * @return array<int, RouteLeg>|null
     */
    private function journey(?int $fromStopId, ?int $toStopId): ?array
    {
        if ($fromStopId === null || $toStopId === null) {
            return null;
        }

        return $this->routes->legsFor($fromStopId, $toStopId);
    }

    /**
     * Offer a ride in the driver's registered vehicle.
     *
     * The body names two stops and nothing else about the road. Which
     * corridor those stops share, and where each sits along it, is resolved
     * here for the same reason the vehicle is - it is a fact about the
     * network, not a claim the driver gets to make.
     *
     * @param  RideDetails  $attributes
     *
     * @throws ValidationException when the driver has no vehicle to seat
     *                             anybody in, or no route runs between the
     *                             two stops.
     */
    public function offer(User $user, array $attributes): Ride
    {
        $vehicle = $this->vehicles->forUser($user);

        if ($vehicle === null) {
            // A 422 rather than a 403: the driver is allowed here, they are
            // simply not ready, and one error shape keeps the client simple.
            throw ValidationException::withMessages([
                'vehicle' => 'Register your vehicle before offering a ride.',
            ]);
        }

        $placement = $this->routes->place(
            (int) $attributes['origin_stop_id'],
            (int) $attributes['destination_stop_id'],
        );

        return $this->rides->create($user, $vehicle, [...$attributes, ...$placement]);
    }

    /**
     * Change a ride that nobody has booked yet.
     *
     * Once a seat is sold the terms are settled: a passenger agreed to that
     * route, that departure and that fare, so the ride stops being editable
     * rather than editing under them. There is no cancellation yet, so this
     * is permanent - the driver's remaining move is to publish another ride.
     *
     * Checking for bookings and then writing is the same read-then-write
     * that booking is, and needs the same protection: without the lock a
     * booking landing between the check and the update would be silently
     * edited underneath, which is exactly what this rule exists to prevent.
     * So both take the ride row - see RideRepository::lockForWrite() - and
     * serialise against each other.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException when the ride already has a booking.
     */
    public function change(Ride $ride, array $attributes): Ride
    {
        return DB::transaction(function () use ($ride, $attributes): Ride {
            $locked = $this->rides->lockForWrite($ride);

            if ($this->bookings->anyOn($locked)) {
                // A 422 keyed on the ride rather than a 409: the client
                // parses one error shape everywhere, as it does for a
                // missing vehicle. Throwing here rolls the edit back whole.
                throw ValidationException::withMessages([
                    'ride' => 'Somebody has already booked this ride, so it can no longer be changed.',
                ]);
            }

            return $this->rides->update($locked, $this->replace($locked, $attributes));
        }, attempts: 3);
    }

    /**
     * Change a ride from the admin panel, booked or not.
     *
     * The driver's own edit closes the moment a seat sells, because the
     * passenger agreed to that route, that departure and that fare. An admin
     * is the deliberate exception: with no cancellation anywhere in the
     * product, a ride published with the wrong departure and three
     * passengers on it would otherwise be unfixable by anybody.
     *
     * Two things it still will not do. It cannot **oversell the vehicle** -
     * `seats_offered` may not fall below the seats passengers already hold,
     * or the free-seat arithmetic every read runs goes negative. And it
     * cannot put the ride somewhere the network does not go: both ends are
     * re-placed through `TravelRouteService` exactly as a driver's edit is,
     * so the corridor and the two sequences stay facts rather than claims.
     *
     * What it *does* do, and what the panel warns about, is move the fare
     * under a booking. `Booking::totalAmount()` multiplies the ride's own
     * `seat_price`, which was safe only while a booked ride could not change
     * it - so changing it here rewrites what every passenger on board owes.
     *
     * Takes the same lock `change()` and `BookingService::book()` take, so a
     * booking landing mid-edit cannot slip under the seat check.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException when the edit would oversell the vehicle,
     *                             or no corridor runs between the two stops.
     */
    public function override(Ride $ride, array $attributes): Ride
    {
        return DB::transaction(function () use ($ride, $attributes): Ride {
            $locked = $this->rides->lockForWrite($ride);

            $taken = $this->bookings->seatsTakenOn($locked);

            if (array_key_exists('seats_offered', $attributes) && (int) $attributes['seats_offered'] < $taken) {
                throw ValidationException::withMessages([
                    'seats_offered' => "Passengers already hold {$taken} seats on this ride, so it cannot offer fewer.",
                ]);
            }

            return $this->rides->update($locked, $this->replace($locked, $attributes));
        }, attempts: 3);
    }

    /**
     * Re-place the ride when an edit moves either end of it.
     *
     * Moving one end can change the corridor - Cumilla to Dhaka becomes
     * Cumilla to Maijdee and the ride is on the Noakhali branch now - so the
     * pair is always resolved together, reading the end that was not sent off
     * the ride. An edit that touches neither is left exactly as it was, which
     * is what keeps a fare correction from re-deriving anything.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function replace(Ride $ride, array $attributes): array
    {
        $moved = array_key_exists('origin_stop_id', $attributes)
            || array_key_exists('destination_stop_id', $attributes);

        if (! $moved) {
            return $attributes;
        }

        $originStopId = $attributes['origin_stop_id'] ?? $ride->origin_stop_id;
        $destinationStopId = $attributes['destination_stop_id'] ?? $ride->destination_stop_id;

        if ($originStopId === null || $destinationStopId === null) {
            /*
             * A ride published before corridors existed has no stop on the
             * end that was left out, so there is nothing to hold the edit
             * against. Both ends have to be named to bring it onto a route.
             */
            throw ValidationException::withMessages([
                'origin_stop_id' => 'This ride predates routes, so send both a start point and a destination.',
            ]);
        }

        return [
            ...$attributes,
            ...$this->routes->place((int) $originStopId, (int) $destinationStopId),
        ];
    }
}
