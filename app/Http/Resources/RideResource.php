<?php

namespace App\Http\Resources;

use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ride
 */
class RideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Each end of the trip is nested rather than flattened, so a client hands
     * `origin` straight to a map marker. Coordinates are numbers; the fare
     * stays a fixed-point string.
     *
     * `driver_rating` is the **one thing about the driver this carries**, and
     * deliberately so: `GET /api/rides` is public, and an aggregate score
     * identifies nobody where a name and a number would. It is attached by a
     * correlated subselect rather than a loaded relation, so there is no
     * `User` on the ride at all - see `RideEloquentRepository`. Absent from
     * a ride built any other way, and null inside it until somebody rates.
     *
     * The label is the ride's own snapshot of the stop it was published
     * against, taken when it was published - so it does not move if the stop
     * is later renamed or corrected. `stop_id` and
     * `sequence` are what a client uses to reason about the corridor, and
     * both are null on a ride published before corridors existed.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Ride $ride */
        $ride = $this->resource;

        // Attached by the queries that select it (withSum); absent on a ride
        // just published, which by definition has no bookings.
        $sum = $ride->getAttribute('bookings_sum_seats');
        $booked = is_numeric($sum) ? (int) $sum : 0;

        // Selected by every query that renders a ride; a ride built without
        // it simply reports no rating rather than claiming a zero.
        $average = $ride->getAttribute('driver_rating_average');
        $count = $ride->getAttribute('driver_ratings_count');

        return [
            'id' => $this->id,
            'route' => new TravelRouteResource($this->whenLoaded('travelRoute')),
            'origin' => [
                'stop_id' => $this->origin_stop_id,
                'label' => $this->origin_label,
                'sequence' => $this->origin_sequence,
            ],
            'destination' => [
                'stop_id' => $this->destination_stop_id,
                'label' => $this->destination_label,
                'sequence' => $this->destination_sequence,
            ],
            'departs_at' => $this->departs_at,
            'seat_price' => $this->seat_price,
            'seats_offered' => $this->seats_offered,
            'seats_booked' => $booked,
            'seats_available' => max(0, $this->seats_offered - $booked),
            'driver_rating' => [
                /*
                 * Formatted here rather than cast on the model: the value
                 * arrives from a raw subselect, so it never passes through
                 * `User`'s `decimal:2`. Without this a ride would report
                 * `4` where every other shape reports `4.00`.
                 */
                'average' => $average === null ? null : number_format((float) $average, 2, '.', ''),
                'count' => is_numeric($count) ? (int) $count : 0,
            ],
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
