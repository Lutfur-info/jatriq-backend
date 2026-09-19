<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ride\SearchRidesRequest;
use App\Http\Resources\RideResource;
use App\Http\Resources\TravelRouteResource;
use App\Services\RideService;
use App\Services\TravelRouteService;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shop window, as a web page.
 *
 * The same rides `GET /api/rides` serves the mobile clients, rendered for
 * somebody who arrived at the site with no account: upcoming, not yet full,
 * soonest first. **Unauthenticated**, like the JSON list, and throttled by
 * address for the same reason - there is no account to key it to.
 *
 * Two differences from the API, both because this end is a browser:
 *
 * - **It pages.** A browser cannot be handed every bookable ride at once.
 *   Paging the JSON list instead would change a response shape those clients
 *   already parse, so the board reads `RideService::availablePage()` and the
 *   API keeps its whole collection.
 * - **It carries the corridors.** The two pickers a passenger searches with
 *   are built from them, so they ship with the page rather than costing a
 *   second request the way `GET /api/routes` does.
 *
 * The search itself is the API's, down to the form request: both stops or
 * neither, and a ride qualifies when it runs the same corridor the same way
 * and its own trip contains the passenger's - so somebody looking for Laksam
 * to Dhaka is shown the vehicle that left Sonaimuri.
 *
 * Like the JSON list, it must never carry anything about the driver: this is
 * a page anybody on the internet can read.
 */
class RideBoardController extends Controller
{
    public function __construct(
        private RideService $rides,
        private TravelRouteService $routes,
    ) {}

    /**
     * The board: one page of bookable rides, and the search over them.
     */
    public function index(SearchRidesRequest $request): Response
    {
        $rides = $this->rides->availablePage(
            $request->fromStopId(),
            $request->toStopId(),
            (int) config('rides.board.per_page'),
        );

        return Inertia::render('rides/index', [
            'rides' => RideResource::collection($rides),
            'routes' => $this->plain(
                TravelRouteResource::collection($this->routes->active())
            ),

            // Echoed back so the pickers come back showing what was asked,
            // which is also what the pagination links carry from page to page.
            'filters' => [
                'from_stop_id' => $request->fromStopId(),
                'to_stop_id' => $request->toStopId(),
            ],
        ]);
    }

    /**
     * One ride in full, behind every card on the board.
     *
     * A ride that has left is a **404**: there is nothing to offer and no
     * page worth rendering. A ride whose seats are all taken is not - it has
     * dropped off the board, but somebody following a link is owed "fully
     * booked" rather than being told the trip never existed.
     */
    public function show(int $ride): Response
    {
        $found = $this->rides->findUpcoming($ride);

        abort_if($found === null, 404);

        return Inertia::render('rides/show', [
            'ride' => $this->plain(new RideResource($found)),
        ]);
    }

    /**
     * A resource as plain data, without the `data` wrapper around it.
     *
     * The wrapper earns its place on the paginated ride list, where it
     * carries the page links and the totals beside the rows. On a flat list
     * of corridors, or on one ride, it is a Laravel detail the page component
     * would otherwise have to know about.
     *
     * The round trip through the response is what resolves the resources
     * *inside* the resource - a corridor's stops, a ride's vehicle. `resolve()`
     * only goes one level down and leaves the rest as objects, which survives
     * being encoded into the page but not being read as an array.
     *
     * @return array<mixed>
     */
    private function plain(JsonResource $resource): array
    {
        /** @var array{data: array<mixed>} $payload */
        $payload = $resource->response()->getData(true);

        return $payload['data'];
    }
}
