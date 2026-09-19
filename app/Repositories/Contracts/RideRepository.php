<?php

namespace App\Repositories\Contracts;

use App\Models\Ride;
use App\Models\TravelRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Persistence for the rides a driver offers.
 *
 * `user_id` and `vehicle_id` are not in the attribute shape: this layer owns
 * both, exactly as it owns the columns no request may mass assign.
 *
 * The corridor columns *are* in the shape but are likewise not fillable - the
 * repository assigns them by hand. They never come from a request body:
 * RideService resolves them from the two stops the driver picked, so a body
 * cannot claim a corridor those stops do not share.
 *
 * @phpstan-type RideAttributes array{
 *     travel_route_id: int,
 *     origin_stop_id: int,
 *     origin_sequence: int,
 *     origin_label: string,
 *     destination_stop_id: int,
 *     destination_sequence: int,
 *     destination_label: string,
 *     departs_at: Carbon,
 *     seat_price: string,
 *     seats_offered: int,
 * }
 *
 * @phpstan-import-type RouteLeg from TravelRouteRepository
 */
interface RideRepository
{
    /**
     * The driver's rides that have not left yet, soonest first.
     *
     * @return Collection<int, Ride>
     */
    public function upcomingForUser(User $user): Collection;

    /**
     * Rides a passenger could still book, soonest first.
     *
     * Upcoming and not yet full: a ride whose seats are all taken is not an
     * option, and neither is one that has left. Carries each ride's vehicle
     * and booked-seat total, so the list renders without a follow-up call.
     *
     * `$legs` is the passenger's journey as TravelRouteRepository resolved
     * it, and the distinction between its two empty-ish values is load
     * bearing:
     *
     * - **null** - no journey was asked for. Every bookable ride, as before.
     * - **[]** - a journey was asked for and no corridor carries it. Nothing
     *   can match, so nothing is returned.
     *
     * A ride covers a leg when it runs on that corridor, the same way round,
     * and its own span *contains* the leg. Every comparison is on the integer
     * sequences copied onto the ride, so this stays one indexed read with no
     * joins through the pivot - and no SQL function the sqlite the suite runs
     * on does not have.
     *
     * @param  array<int, RouteLeg>|null  $legs
     * @return Collection<int, Ride>
     */
    public function available(?array $legs = null): Collection;

    /**
     * The same rides, one page at a time.
     *
     * What the public web board reads. `available()` stays whole because the
     * JSON API hands a client every bookable ride in one response and its
     * shape is not ours to change; a browser cannot render that, so paging
     * is a second method over the same rules rather than a flag on the first.
     *
     * `$legs` means exactly what it means there, empty array included.
     *
     * @param  array<int, RouteLeg>|null  $legs
     * @return LengthAwarePaginator<int, Ride>
     */
    public function availablePage(?array $legs, int $perPage): LengthAwarePaginator;

    /**
     * One ride that has not left yet, with its vehicle and booked seats.
     *
     * Null when no such ride exists *or* it has already departed - the two
     * are the same answer to somebody holding a link, and the caller turns
     * either into a 404. A full ride is deliberately still found: it has
     * dropped off the board, but the page can say so rather than pretend the
     * trip never existed.
     */
    public function findUpcoming(int $rideId): ?Ride;

    /**
     * Record a ride the driver is offering in the given vehicle.
     *
     * @param  RideAttributes  $attributes
     */
    public function create(User $user, Vehicle $vehicle, array $attributes): Ride;

    /**
     * Change the details of a ride, field by field.
     *
     * A partial set: only the keys present are written, so an edit of the
     * fare leaves both ends of the trip exactly as they were.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Ride $ride, array $attributes): Ride;

    /**
     * Re-read the ride under a row lock, for the length of a transaction.
     *
     * The ride row is the one place every writer queues. Two of them read
     * state and then act on it, which is only safe if nobody moves in
     * between:
     *
     * - booking counts the seats already taken and then takes more, so two
     *   passengers reaching for the last seat must not both succeed;
     * - editing checks that nobody has booked and then writes, so a booking
     *   landing in between must not slip past the guard.
     *
     * Taking the same lock in both makes them serialise against each other:
     * either the edit lands before any booking, or the booking wins and the
     * edit is refused. Callers must be inside a transaction, or the lock is
     * released the moment the statement ends.
     */
    public function lockForWrite(Ride $ride): Ride;

    /**
     * Re-read the ride with its vehicle and its booked-seat total attached.
     */
    public function withBookedSeats(Ride $ride): Ride;

    /**
     * Move every ride on one corridor onto that corridor's new positions.
     *
     * A ride carries a **copy** of its two sequences, taken when it was
     * published, so renumbering a corridor without this leaves every ride on
     * it pointing at positions the road no longer has - still listed, but out
     * of every search that reads the corridor. The two must move together, in
     * one transaction.
     *
     * Rides published before corridors existed carry no stop ids and are
     * skipped: there is nothing to look their new positions up by.
     *
     * @param  array<int, int>  $sequenceByStopId
     * @return int how many rides were rewritten.
     */
    public function resequenceOnCorridor(TravelRoute $route, array $sequenceByStopId): int;
}
