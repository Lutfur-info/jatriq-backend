<?php

namespace App\Http\Resources;

use App\Models\TravelRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A corridor, as the ordered list of stops a client builds its pickers from.
 *
 * The model is `TravelRoute` only so it never collides with Laravel's Route
 * facade; to every client this is a route, and the key it arrives under says
 * so.
 *
 * @mixin TravelRoute
 */
class TravelRouteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Stops arrive **in travel order, outbound from Dhaka**, and the sequence
     * on each is what makes "before" and "after" meaningful. A passenger
     * boarding at a later stop than a ride's origin is exactly what the
     * search is built on, so the order is the payload, not decoration.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'stops' => StopResource::collection($this->whenLoaded('stops')),
        ];
    }
}
