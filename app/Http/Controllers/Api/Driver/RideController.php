<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\StoreRideRequest;
use App\Http\Requests\Api\Driver\UpdateRideRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use App\Models\User;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The rides a driver offers: a start point, a destination, a departure time
 * and a price per passenger seat.
 *
 * Driver only, for the same reason the vehicle is: the seats being sold are
 * that driver's vehicle's seats. A ride is always in the vehicle already
 * registered, so the request never names one - RideService resolves it, and
 * refuses a driver who has not registered one at all.
 *
 * Unlike the vehicle there may be many, so this has a list, an id-less
 * create and an edit. The edit closes as soon as anybody books: a passenger
 * agreed to that route, that departure and that fare. There is no cancel or
 * delete.
 */
class RideController extends Controller
{
    public function __construct(private RideService $rides) {}

    /**
     * The driver's own rides that have not left yet, soonest first.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rides = $this->rides->upcomingFor($user);

        return response()->json([
            'message' => $rides->isEmpty()
                ? 'No upcoming rides.'
                : 'Your upcoming rides.',
            'data' => [
                'rides' => RideResource::collection($rides),
            ],
        ]);
    }

    /**
     * Offer a ride.
     */
    public function store(StoreRideRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ride = $this->rides->offer($user, $request->attributesForRide());

        return response()->json([
            'message' => 'Ride published.',
            'data' => [
                'ride' => new RideResource($ride),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Change a ride, while nobody has booked it.
     */
    public function update(UpdateRideRequest $request, Ride $ride): JsonResponse
    {
        $this->authorizeRide($request, $ride);

        $changed = $this->rides->change($ride, $request->attributesForRide());

        return response()->json([
            'message' => 'Ride updated.',
            'data' => [
                'ride' => new RideResource($changed),
            ],
        ]);
    }

    /**
     * Refuse to touch somebody else's ride.
     *
     * A 404 rather than a 403, as an emergency contact does: whether a given
     * id exists is not the caller's business when the row is not theirs.
     */
    private function authorizeRide(Request $request, Ride $ride): void
    {
        abort_unless($ride->user_id === $request->user()?->getAuthIdentifier(), 404);
    }
}
