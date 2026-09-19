<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A driver a passenger has kept, as her favourites list shows them.
 *
 * `RiderResource` plus the car - a favourites list is for picking who to
 * ride with next, and the vehicle is what a passenger recognises. The score
 * is here for the same reason: it is most of why she kept him. The person
 * half is deliberately the same four fields, and carries the same rule:
 * **no contact details, in either direction.** A favourite is a private note
 * a passenger keeps, and it must not become a way to collect phone numbers
 * by tapping a heart.
 *
 * The driver is never told they have been favourited. `User::favouritedBy()`
 * exists but nothing reads it - that would be its own privacy decision.
 *
 * `favourited_at` is the pivot's `created_at`, which is why the relation is
 * declared `withTimestamps()`; it is also what the list is ordered by.
 *
 * @mixin User
 */
class FavouriteDriverResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $driver */
        $driver = $this->resource;

        return [
            'id' => $this->id,
            'name' => $driver->full_name,
            'verification_status' => $driver->verification_status->name,
            'verification_status_label' => $driver->verification_status->getLabel(),
            'rating' => [
                'average' => $driver->rating_average,
                'count' => $driver->ratings_count,
            ],
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'favourited_at' => $driver->getAttribute('pivot')?->getAttribute('created_at'),
        ];
    }
}
