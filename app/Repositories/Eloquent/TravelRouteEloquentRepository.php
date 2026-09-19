<?php

namespace App\Repositories\Eloquent;

use App\Models\Stop;
use App\Models\TravelRoute;
use App\Repositories\Contracts\TravelRouteRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-import-type RouteLeg from TravelRouteRepository
 */
class TravelRouteEloquentRepository implements TravelRouteRepository
{
    /**
     * {@inheritDoc}
     */
    public function active(): Collection
    {
        return TravelRoute::query()
            ->where('is_active', true)
            /*
             * The relation already orders by sequence, and a retired town is
             * dropped from the picker without touching the rides on it. The
             * district comes along because StopResource prints its name, and
             * a corridor has fifty towns on it.
             */
            ->with(['stops' => fn ($stops) => $stops->where('stops.is_active', true)->with('district')])
            ->orderBy('name')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function legsBetween(int $fromStopId, int $toStopId): array
    {
        // Not a journey, so no corridor carries it. Guarded here rather than
        // left to the self join, which would happily return every corridor
        // the stop is on with both sequences equal.
        if ($fromStopId === $toStopId) {
            return [];
        }

        /*
         * The pivot joined to itself on the corridor: one row per corridor
         * carrying both stops, with each end's position on it. A stop on
         * three corridors and a destination on two of them yields two rows.
         */
        $legs = DB::table('travel_route_stop as boarding')
            ->join(
                'travel_route_stop as alighting',
                'alighting.travel_route_id',
                '=',
                'boarding.travel_route_id',
            )
            ->join('travel_routes', 'travel_routes.id', '=', 'boarding.travel_route_id')
            ->where('boarding.stop_id', $fromStopId)
            ->where('alighting.stop_id', $toStopId)
            ->where('travel_routes.is_active', true)
            ->select([
                'boarding.travel_route_id',
                'boarding.sequence as from_sequence',
                'alighting.sequence as to_sequence',
            ])
            ->get();

        return $legs->map(fn (object $leg): array => [
            'travel_route_id' => (int) $leg->travel_route_id,
            'from_sequence' => (int) $leg->from_sequence,
            'to_sequence' => (int) $leg->to_sequence,
        ])->all();
    }

    /**
     * {@inheritDoc}
     */
    public function placementsOn(TravelRoute $route): array
    {
        // Straight off the pivot rather than through the relation: this is
        // arithmetic input, so it wants every town on the road - a retired
        // one still occupies its number.
        $placements = DB::table('travel_route_stop')
            ->join('stops', 'stops.id', '=', 'travel_route_stop.stop_id')
            ->where('travel_route_stop.travel_route_id', $route->getKey())
            ->orderBy('travel_route_stop.sequence')
            ->select(['stops.id', 'stops.name', 'travel_route_stop.sequence'])
            ->get();

        return $placements->map(fn (object $placement): array => [
            'id' => (int) $placement->id,
            'name' => (string) $placement->name,
            'sequence' => (int) $placement->sequence,
        ])->all();
    }

    /**
     * {@inheritDoc}
     */
    public function attachStop(TravelRoute $route, Stop $stop, int $sequence): void
    {
        $route->stops()->attach($stop->getKey(), ['sequence' => $sequence]);
    }

    /**
     * {@inheritDoc}
     */
    public function moveStop(TravelRoute $route, Stop $stop, int $sequence): void
    {
        $route->stops()->updateExistingPivot($stop->getKey(), ['sequence' => $sequence]);
    }

    /**
     * {@inheritDoc}
     */
    public function detachStop(TravelRoute $route, Stop $stop): void
    {
        $route->stops()->detach($stop->getKey());
    }

    /**
     * {@inheritDoc}
     */
    public function resequence(TravelRoute $route, array $sequenceByStopId): void
    {
        /*
         * Cleared and re-laid rather than updated in place: the unique index
         * on (travel_route_id, sequence) rejects the intermediate states an
         * in-place shuffle passes through, and no ordering of the writes
         * avoids that in general. Nothing hangs off a pivot row's id or
         * timestamps, so re-creating them costs nothing.
         *
         * The caller wraps this in a transaction - see
         * `TravelRouteService::renumber()`, which rewrites the rides in the
         * same one.
         */
        $route->stops()->detach();

        foreach ($sequenceByStopId as $stopId => $sequence) {
            $route->stops()->attach($stopId, ['sequence' => $sequence]);
        }
    }
}
