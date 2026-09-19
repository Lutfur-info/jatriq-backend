<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\RateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a passenger thought of a trip she took.
 *
 * `POST` creates or replaces, like the driver's vehicle: she has one opinion
 * of a trip and may change her mind, so there is no separate edit and no
 * rating id in the path - the booking is the trip.
 *
 * Somebody else's booking is a **404**, as a ride is: whether a given id
 * exists is not the caller's business when the row is not theirs. Rating is
 * gated on having *travelled*, which is a stronger thing than the identity
 * badge, so `verified.identity` would be redundant here - she could not hold
 * a confirmed booking without it.
 */
class BookingRatingController extends Controller
{
    public function __construct(private RatingService $ratings) {}

    /**
     * Score a trip out of five.
     */
    public function store(RateBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorizeBooking($request, $booking);

        $rated = $this->ratings->rate($booking, $request->rating());

        return response()->json([
            'message' => 'Thanks for rating this trip.',
            'data' => [
                'booking' => new BookingResource($rated),
            ],
        ]);
    }

    /**
     * Refuse to touch a booking that is not the caller's.
     */
    private function authorizeBooking(Request $request, Booking $booking): void
    {
        abort_unless($booking->user_id === $request->user()?->getAuthIdentifier(), 404);
    }
}
