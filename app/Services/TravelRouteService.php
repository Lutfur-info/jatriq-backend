<?php

namespace App\Services;

use App\Models\Stop;
use App\Models\TravelRoute;
use App\Repositories\Contracts\RideRepository;
use App\Repositories\Contracts\StopRepository;
use App\Repositories\Contracts\TravelRouteRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The corridors rides run on, and where a given journey sits on them.
 *
 * Two jobs, both about the road rather than about the ride:
 *
 * - **Placing a trip.** A driver picks two stops; this works out which
 *   corridor they share and where each falls along it. The ride never names a
 *   corridor, exactly as it never names a vehicle.
 * - **Resolving a search.** A passenger names two stops; this hands back every
 *   corridor that carries them, which RideService turns into a list of rides.
 * - **Building the road.** An admin puts a town on a corridor, moves it or
 *   takes it off. Nobody picks a sequence number by hand: a placement names
 *   two neighbours ("between Dhaka and Cumilla") and the number is worked out
 *   from the gap between them, which is what keeps the tens intact and makes
 *   a renumber - the one operation that would mis-position published rides -
 *   something this service can refuse rather than something an admin can
 *   type by accident.
 *
 * @phpstan-import-type RouteLeg from TravelRouteRepository
 *
 * @phpstan-type RidePlacement array{
 *     travel_route_id: int,
 *     origin_stop_id: int,
 *     origin_sequence: int,
 *     origin_label: string,
 *     destination_stop_id: int,
 *     destination_sequence: int,
 *     destination_label: string,
 * }
 */
class TravelRouteService
{
    /**
     * The gap the seeder leaves between towns, and the one this service
     * leaves past the end of a road. See `TravelRouteSeeder`.
     */
    private const SPACING = 10;

    /**
     * `travel_route_stop.sequence` is an unsigned smallint.
     */
    private const CEILING = 65535;

    public function __construct(
        private TravelRouteRepository $routes,
        private StopRepository $stops,
        private RideRepository $rides,
    ) {}

    /**
     * Every corridor still on offer, with its stops in travel order.
     *
     * What the two pickers are built from, which is why the order matters
     * enough to live in the relation rather than in each caller.
     *
     * @return Collection<int, TravelRoute>
     */
    public function active(): Collection
    {
        return $this->routes->active();
    }

    /**
     * Every corridor that carries a journey between these two stops.
     *
     * Plural on purpose: the road out of Dhaka as far as Cumilla is shared by
     * the Chattogram, Cox's Bazar and Noakhali corridors, so a Cumilla to
     * Dhaka search has three readings and a ride coming down any of them
     * serves it.
     *
     * @return array<int, RouteLeg>
     */
    public function legsFor(int $fromStopId, int $toStopId): array
    {
        return $this->routes->legsBetween($fromStopId, $toStopId);
    }

    /**
     * Work out which corridor a trip between two stops runs on.
     *
     * Returns the corridor, both sequences, and a **snapshot** of each stop's
     * name and place id. The snapshot is why renaming a stop later never
     * rewrites a trip a passenger already agreed to - and why BookingResource
     * can read `origin_name` off the ride without loading anything.
     *
     * @return RidePlacement
     *
     * @throws ValidationException when the two stops share no corridor, or
     *                             either one is unknown.
     */
    public function place(int $originStopId, int $destinationStopId): array
    {
        $origin = $this->stops->find($originStopId);
        $destination = $this->stops->find($destinationStopId);

        if ($origin === null || $destination === null) {
            // A 422 keyed on a field, like every other refusal in this API,
            // rather than a 404 for a row the driver never addressed.
            throw ValidationException::withMessages([
                'destination_stop_id' => 'Pick both a start point and a destination.',
            ]);
        }

        $legs = $this->routes->legsBetween($origin->id, $destination->id);

        if ($legs === []) {
            throw ValidationException::withMessages([
                'destination_stop_id' => 'No route runs between those two points.',
            ]);
        }

        $leg = $this->mostDirect($legs);

        /*
         * Written out rather than built from a loop over the two ends: the
         * shape is the contract RideRepository fills a ride from, and a
         * helper producing dynamic keys would hide a missing column until
         * runtime.
         */
        return [
            'travel_route_id' => $leg['travel_route_id'],

            'origin_stop_id' => $origin->id,
            'origin_sequence' => $leg['from_sequence'],
            'origin_label' => $origin->name,

            'destination_stop_id' => $destination->id,
            'destination_sequence' => $leg['to_sequence'],
            'destination_label' => $destination->name,
        ];
    }

    /**
     * The corridor on which the two stops are closest together.
     *
     * A trip between two towns on the shared stretch out of Dhaka belongs to
     * several corridors at once and something has to choose. Fewest stops
     * between the two ends is the most specific reading; the route id breaks
     * a genuine tie so the same input always resolves the same way.
     *
     * **The choice is cosmetic.** Whichever corridor is picked, a passenger
     * only matches this ride through a leg on that same corridor, and the
     * shared stretch carries the same towns on all of them - so the rides
     * that come back are identical either way. It decides which corridor
     * *name* the ride is displayed under, nothing more.
     *
     * @param  array<int, RouteLeg>  $legs
     * @return RouteLeg
     */
    private function mostDirect(array $legs): array
    {
        usort($legs, function (array $first, array $second): int {
            $span = abs($first['to_sequence'] - $first['from_sequence'])
                <=> abs($second['to_sequence'] - $second['from_sequence']);

            return $span !== 0
                ? $span
                : $first['travel_route_id'] <=> $second['travel_route_id'];
        });

        return $legs[0];
    }

    /**
     * Where a town could go on this corridor, in road order.
     *
     * The keys are placements, not numbers: `start`, `after:{stop id}` and
     * `end`. An admin picks the two towns it should sit between and never
     * sees a sequence - the whole point, because a hand-typed number is how a
     * corridor gets renumbered by accident.
     *
     * A placement with no room left is still listed, labelled as full, so the
     * gap that needs re-planning is visible rather than silently missing.
     *
     * @param  Stop|null  $moving  A town already on the corridor that is being
     *                             repositioned; it is left out of its own
     *                             neighbour list.
     * @return array<string, array{label: string, sequence: int|null}>
     */
    public function placementsOn(TravelRoute $route, ?Stop $moving = null): array
    {
        $towns = $this->townsOn($route, $moving);

        if ($towns === []) {
            return ['end' => [
                'label' => 'First town on this corridor',
                'sequence' => self::SPACING,
            ]];
        }

        $first = $towns[0];
        $last = $towns[count($towns) - 1];

        $placements = ['start' => [
            'label' => "At the start, before {$first['name']}",
            'sequence' => $this->roomBefore($first['sequence']),
        ]];

        foreach ($towns as $index => $town) {
            $next = $towns[$index + 1] ?? null;

            if ($next === null) {
                continue;
            }

            $placements["after:{$town['id']}"] = [
                'label' => "Between {$town['name']} and {$next['name']}",
                'sequence' => $this->roomBetween($town['sequence'], $next['sequence']),
            ];
        }

        $placements['end'] = [
            'label' => "At the end, after {$last['name']}",
            'sequence' => $this->roomAfter($last['sequence']),
        ];

        return $placements;
    }

    /**
     * The number a placement works out to, or null when it is unusable.
     *
     * Also accepts `exact:{n}`, which is not offered in `placementsOn()`
     * because it is the escape hatch rather than a gap: it is checked only
     * for being inside the column and free on this corridor, so an admin who
     * knows the road can put a town at 25 without being talked through the
     * neighbours. It still cannot land on a number another town holds.
     */
    public function sequenceFor(TravelRoute $route, string $placement, ?Stop $moving = null): ?int
    {
        if (str_starts_with($placement, 'exact:')) {
            return $this->exactSequence($route, (int) substr($placement, strlen('exact:')), $moving);
        }

        return $this->placementsOn($route, $moving)[$placement]['sequence'] ?? null;
    }

    /**
     * Re-space a corridor in tens, moving the rides on it with the road.
     *
     * The operation the placement dropdown deliberately cannot do. It exists
     * for the one case that dropdown has to refuse - a gap between two towns
     * with no whole number left in it - and it is safe only because it
     * rewrites `origin_sequence` and `destination_sequence` on every ride
     * published along the corridor in the same transaction. Renumbering
     * without that leaves those rides pointing at positions the road no
     * longer has: still listed, invisible to every search.
     *
     * The towns keep their order. Only the numbers between them change.
     *
     * @return array{towns: int, rides: int}
     */
    public function renumber(TravelRoute $route): array
    {
        $towns = $this->townsOn($route);

        if ($towns === []) {
            return ['towns' => 0, 'rides' => 0];
        }

        $sequenceByStopId = [];

        foreach ($towns as $index => $town) {
            $sequenceByStopId[$town['id']] = ($index + 1) * self::SPACING;
        }

        return DB::transaction(function () use ($route, $sequenceByStopId): array {
            $this->routes->resequence($route, $sequenceByStopId);

            return [
                'towns' => count($sequenceByStopId),
                'rides' => $this->rides->resequenceOnCorridor($route, $sequenceByStopId),
            ];
        });
    }

    /**
     * Whether re-spacing this corridor would change anything.
     *
     * A road already sitting on 10, 20, 30 has nothing to gain and the rides
     * on it nothing to risk, so the action says so instead of running.
     */
    public function isEvenlySpaced(TravelRoute $route): bool
    {
        foreach ($this->townsOn($route) as $index => $town) {
            if ($town['sequence'] !== ($index + 1) * self::SPACING) {
                return false;
            }
        }

        return true;
    }

    /**
     * A number typed by hand: inside the column, and free on this corridor.
     */
    private function exactSequence(TravelRoute $route, int $sequence, ?Stop $moving): ?int
    {
        if ($sequence < 1 || $sequence > self::CEILING) {
            return null;
        }

        foreach ($this->townsOn($route, $moving) as $town) {
            if ($town['sequence'] === $sequence) {
                return null;
            }
        }

        return $sequence;
    }

    /**
     * Put a town on a corridor between the two neighbours named.
     *
     * @return int the position it landed at.
     *
     * @throws ValidationException when that gap has no whole number left in
     *                             it. Widening it means renumbering the
     *                             corridor, which would mis-position every
     *                             ride already published on it, so it is a
     *                             decision rather than a side effect.
     */
    public function putStopOn(TravelRoute $route, Stop $stop, string $placement): int
    {
        $sequence = $this->demandRoom($route, $placement);

        $this->routes->attachStop($route, $stop, $sequence);

        return $sequence;
    }

    /**
     * Move a town already on the corridor to another place along it.
     *
     * Moving the town does **not** move the rides published from it: each
     * carries a copy of both sequences. That is the price of never
     * renumbering, and it is why this places one town rather than offering
     * a reorder of the road.
     *
     * @return int the position it landed at.
     *
     * @throws ValidationException when that gap has no whole number left.
     */
    public function moveStopOn(TravelRoute $route, Stop $stop, string $placement): int
    {
        $sequence = $this->demandRoom($route, $placement, $stop);

        $this->routes->moveStop($route, $stop, $sequence);

        return $sequence;
    }

    /**
     * Take a town off a corridor, leaving the town itself alone.
     */
    public function takeStopOff(TravelRoute $route, Stop $stop): void
    {
        $this->routes->detachStop($route, $stop);
    }

    /**
     * The placement's number, or a 422 naming the gap that is full.
     *
     * @throws ValidationException
     */
    private function demandRoom(TravelRoute $route, string $placement, ?Stop $moving = null): int
    {
        $sequence = $this->sequenceFor($route, $placement, $moving);

        if ($sequence === null) {
            throw ValidationException::withMessages([
                'placement' => 'There is no room left between those two towns. Widening the gap means renumbering the corridor, which would mis-position the rides already published on it.',
            ]);
        }

        return $sequence;
    }

    /**
     * The corridor's towns in road order, optionally without one of them.
     *
     * @return array<int, array{id: int, name: string, sequence: int}>
     */
    private function townsOn(TravelRoute $route, ?Stop $without = null): array
    {
        $towns = $this->routes->placementsOn($route);

        if ($without === null) {
            return $towns;
        }

        return array_values(array_filter(
            $towns,
            fn (array $town): bool => $town['id'] !== $without->getKey(),
        ));
    }

    /**
     * Room in front of the first town: half of whatever it sits at.
     */
    private function roomBefore(int $first): ?int
    {
        $sequence = intdiv($first, 2);

        return $sequence >= 1 ? $sequence : null;
    }

    /**
     * Room between two towns: the middle of the gap, when there is one.
     */
    private function roomBetween(int $before, int $after): ?int
    {
        $sequence = intdiv($before + $after, 2);

        return $sequence > $before ? $sequence : null;
    }

    /**
     * Room past the last town: another ten, up to the column's ceiling.
     */
    private function roomAfter(int $last): ?int
    {
        return $last < self::CEILING
            ? min($last + self::SPACING, self::CEILING)
            : null;
    }
}
