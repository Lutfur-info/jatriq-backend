<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ride\SearchRidesRequest;
use App\Http\Resources\RideResource;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;

/**
 * The rides anybody can still book, and the search over them.
 *
 * **Unauthenticated**, and the only read in the API that is: the home screen
 * shows upcoming rides to somebody who has not signed up yet, and signing in
 * is what booking a seat asks for. A ride appears while it has not left and
 * still has a seat free, soonest first, and each carries its vehicle and seat
 * counts so a client renders the whole card without a second call.
 *
 * Give it `from_stop_id` and `to_stop_id` and it answers the question the
 * product exists to answer: which vehicles will pass through where I am,
 * heading where I am going. A ride qualifies when it runs the same corridor
 * the same way and its trip contains the passenger's - so somebody looking
 * for Laksam to Dhaka is shown the vehicle that left Sonaimuri, which still
 * has Laksam ahead of it. Send neither and nothing is filtered.
 *
 * Because it is public it is throttled by address (`throttle:rides-browse`)
 * and it must never carry anything about the driver - see `bookings.md`.
 */
class AvailableRideController extends Controller
{
    public function __construct(private RideService $rides) {}

    /**
     * List the rides with seats still free, optionally along one journey.
     */
    public function index(SearchRidesRequest $request): JsonResponse
    {
        $rides = $this->rides->available(
            $request->fromStopId(),
            $request->toStopId(),
        );

        $searching = $request->fromStopId() !== null;

        return response()->json([
            'message' => match (true) {
                ! $rides->isEmpty() => 'Rides you can book.',
                $searching => 'No rides running that way right now.',
                default => 'No rides available right now.',
            },
            'data' => [
                'rides' => RideResource::collection($rides),
            ],
        ]);
    }
}
