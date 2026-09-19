<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TravelRouteResource;
use App\Services\TravelRouteService;
use Illuminate\Http\JsonResponse;

/**
 * The corridors rides run on, each as its stops in travel order.
 *
 * **Unauthenticated**, like the ride list beside it and for the same reason:
 * the home screen offers a "from" and a "to" picker before anybody has an
 * account, and both are built from this. It is reference data - the same
 * answer for every caller - so there is nothing here to protect.
 *
 * The order of `stops` is the payload. Sequence runs outbound from Dhaka, and
 * it is what makes a stop "before" another one on the road; a client that
 * re-sorts them alphabetically has thrown the meaning away.
 */
class TravelRouteController extends Controller
{
    public function __construct(private TravelRouteService $routes) {}

    /**
     * List the corridors and their stops.
     */
    public function index(): JsonResponse
    {
        $routes = $this->routes->active();

        return response()->json([
            'message' => 'Routes we run.',
            'data' => [
                'routes' => TravelRouteResource::collection($routes),
            ],
        ]);
    }
}
