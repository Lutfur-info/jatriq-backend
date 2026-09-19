<?php

namespace App\Repositories\Contracts;

use App\Models\Stop;
use App\Models\TravelRoute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

/**
 * The highway corridors and where each stop falls along them.
 *
 * A **leg** is the answer to "if I board at A and get off at B, which
 * corridor am I on and where do the two ends sit?". There can be more than
 * one: Cumilla to Dhaka is a leg of the Chattogram corridor, of the Noakhali
 * corridor and of the Cox's Bazar corridor, because the road out of Dhaka is
 * the same one for all three. Every caller therefore deals in a list.
 *
 * `from_sequence` and `to_sequence` are read in the corridor's canonical
 * order, which runs **outbound from Dhaka**. So a leg toward Dhaka has a
 * `to_sequence` below its `from_sequence`, and comparing the two is the only
 * thing that says which way the traveller is going.
 *
 * @phpstan-type RouteLeg array{
 *     travel_route_id: int,
 *     from_sequence: int,
 *     to_sequence: int,
 * }
 * @phpstan-type Placement array{
 *     id: int,
 *     name: string,
 *     sequence: int,
 * }
 */
interface TravelRouteRepository
{
    /**
     * Every corridor still on offer, with its stops in order.
     *
     * @return Collection<int, TravelRoute>
     */
    public function active(): Collection;

    /**
     * Every corridor that carries both stops, and where each sits on it.
     *
     * Empty when no corridor carries both - two towns on different roads
     * with no through service - and empty for two identical stops, which is
     * not a journey.
     *
     * @return array<int, RouteLeg>
     */
    public function legsBetween(int $fromStopId, int $toStopId): array;

    /**
     * The towns on one corridor in road order, with their positions.
     *
     * Flattened to id, name and sequence because the caller doing the
     * arithmetic - `TravelRouteService` - is not allowed to reach for the
     * pivot itself, and an ordered list of three scalars is the whole of
     * what placing a town needs.
     *
     * @return array<int, Placement> ordered outbound from Dhaka.
     */
    public function placementsOn(TravelRoute $route): array;

    /**
     * Put a town on a corridor at a position nothing else holds.
     *
     * @throws QueryException when the position is taken.
     */
    public function attachStop(TravelRoute $route, Stop $stop, int $sequence): void;

    /**
     * Move a town already on the corridor to another position on it.
     */
    public function moveStop(TravelRoute $route, Stop $stop, int $sequence): void;

    /**
     * Take a town off a corridor. The town itself is untouched.
     */
    public function detachStop(TravelRoute $route, Stop $stop): void;

    /**
     * Rewrite every position on one corridor in a single pass.
     *
     * The pivot is unique on `(travel_route_id, sequence)`, so positions
     * cannot be shuffled row by row - the first write would collide with a
     * number the second has not vacated yet. The implementation clears the
     * corridor and lays it out again inside the caller's transaction.
     *
     * @param  array<int, int>  $sequenceByStopId
     */
    public function resequence(TravelRoute $route, array $sequenceByStopId): void;
}
