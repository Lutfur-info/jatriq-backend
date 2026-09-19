<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Flat on purpose: this is what a passenger's booking reads as on the
     * screen - how many seats, what it came to, which car, and between which
     * two places. The ride's own shape (place ids, sequences, seat
     * inventory) is not part of it; `RideResource` is where that lives, and
     * the create response still carries one alongside.
     *
     * `seats` is her running total on the ride, since booking again tops up
     * the same booking rather than making another.
     *
     * `rating` is hers to the trip, the mirror of `status` being his to her
     * request. `can_rate` is the server's answer to whether she may leave
     * one - there is no ride lifecycle, so it reads the departure and the
     * confirmation rather than a status nobody sets.
     *
     * `status` is the driver's answer, and the reason the flat shape carries
     * a label beside the case name: "Awaiting the driver" is what a screen
     * shows, and no client should be spelling that out from an enum.
     *
     * `passenger` and `driver` are each present only where the relation was
     * loaded, which is the opposite end from whoever is asking: the driver's
     * queue names the passenger, and her own list names the driver. Both are
     * `RiderResource`, which carries **no contact details in either
     * direction** - a name and a badge, and nothing to phone.
     *
     * The driver appearing here at all is new (2026-09-19). It is what makes
     * favouriting one possible from the app, because nothing else a
     * passenger can open shows her who drove: `RideResource` deliberately
     * carries no driver, since `GET /api/rides` is public.
     *
     * The ride and its vehicle have to be loaded - every caller does.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Booking $booking */
        $booking = $this->resource;

        return [
            'id' => $this->id,
            'seats' => $this->seats,
            'status' => $booking->status->name,
            'status_label' => $booking->status->getLabel(),
            'decided_at' => $this->decided_at,
            'rating' => $this->rating,
            'rated_at' => $this->rated_at,
            /*
             * Whether she may score this trip. Answered by the server rather
             * than worked out by each client from a departure time and a
             * status, because the rule is the server's and "rate this trip"
             * appearing on a trip that refuses the rating is the worst way
             * to find out it moved.
             */
            'can_rate' => $booking->isRateable(),
            'total_amount' => $booking->totalAmount(),
            'vehicle_number' => $booking->ride->vehicle->registration_number,
            'origin_name' => $booking->ride->origin_label,
            'destination_name' => $booking->ride->destination_label,
            'departs_at' => $booking->ride->departs_at,
            'passenger' => new RiderResource($this->whenLoaded('user')),
            'driver' => $this->when(
                $booking->ride->relationLoaded('user'),
                fn (): RiderResource => new RiderResource($booking->ride->user),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
