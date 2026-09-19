<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\DecideBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Ride;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The requests for seats on a driver's ride, and the driver's answer.
 *
 * A seat is asked for, not taken: the driver is letting a stranger into
 * their car, so every booking arrives `Pending` and waits here. A pending
 * request holds its seats throughout - declining is what gives one back -
 * which is what stops a driver confirming more seats than the car has.
 *
 * Driver only, and only their own ride: somebody else's is a **404**, the
 * same answer `RideController` gives, because whether a given id exists is
 * not the caller's business when the row is not theirs.
 *
 * The queue is deliberately readable without the identity badge, exactly as
 * `GET /driver/rides` is - a driver whose badge has lapsed can still see who
 * is waiting on them. Answering needs it, because the answer is what puts a
 * stranger in the car.
 */
class RideBookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    /**
     * Who has asked for a seat on this ride, waiting ones first.
     *
     * Declined requests stay in the list: "I already said no to this person"
     * is the thing a driver most needs to see.
     */
    public function index(Request $request, Ride $ride): JsonResponse
    {
        $this->authorizeRide($request, $ride);

        $bookings = $this->bookings->listForRide($ride);

        return response()->json([
            'message' => $bookings->isEmpty()
                ? 'Nobody has asked for a seat yet.'
                : 'Requests for seats on this ride.',
            'data' => [
                'bookings' => BookingResource::collection($bookings),
            ],
        ]);
    }

    /**
     * Confirm or decline one request.
     */
    public function update(DecideBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorizeRide($request, $booking->ride);

        $status = $request->status();
        $decided = $this->bookings->decide($booking, $status);

        return response()->json([
            'message' => $status->name === 'Confirmed'
                ? 'Seat confirmed.'
                : 'Request declined, and the seats are back on sale.',
            'data' => [
                'booking' => new BookingResource($decided->load('user')),
            ],
        ]);
    }

    /**
     * Refuse to touch a ride that is not the caller's.
     *
     * A 404 rather than a 403, as a ride and an emergency contact both do.
     */
    private function authorizeRide(Request $request, Ride $ride): void
    {
        abort_unless($ride->user_id === $request->user()?->getAuthIdentifier(), 404);
    }
}
