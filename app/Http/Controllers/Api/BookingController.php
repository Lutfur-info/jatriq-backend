<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seats a passenger takes on a driver's ride.
 *
 * Passenger only - the API's one role:Passenger endpoint. A driver offering
 * seats and a driver taking one are different enough to keep apart, and it
 * also means the ride's own driver can never book it.
 *
 * A passenger holds at most one booking per ride: booking again tops up the
 * seats on it rather than adding a second, which is why a repeat answers 200
 * where the first answered 201.
 */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    /**
     * Every booking the passenger holds, upcoming trips first.
     *
     * All of them, including trips already taken - a booking is the record
     * that she travelled, not only that she is about to. Each carries its
     * ride and that ride's vehicle, so this is one call.
     *
     * Reading her own bookings does not need the identity badge, the same
     * way a driver can always list their own rides: it is the booking that
     * needs verifying, not the looking.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $bookings = $this->bookings->listFor($user);

        return response()->json([
            'message' => $bookings->isEmpty()
                ? 'No bookings yet.'
                : 'Your bookings.',
            'data' => [
                'bookings' => BookingResource::collection($bookings),
            ],
        ]);
    }

    /**
     * Book seats on a ride.
     */
    public function store(StoreBookingRequest $request, Ride $ride): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $booking = $this->bookings->book($ride, $user, $request->seats());

        $created = $booking->wasRecentlyCreated;

        return response()->json([
            'message' => $created
                ? 'Seats booked.'
                : 'Seats added to your booking.',
            'data' => [
                'booking' => new BookingResource($booking),
                'ride' => new RideResource($this->bookings->rideAfterBooking($ride)),
            ],
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
