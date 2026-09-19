<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The little a rider is shown about the person at the other end of a trip.
 *
 * Deliberately **not** `UserResource`, which is the shape a user is returned
 * their own account in: that carries email, msisdn and date of birth, which
 * is far more than one rider should receive about another.
 *
 * The driver's **score** rides along with it, because "who am I riding
 * with" is exactly the question a rating answers - and it is an aggregate,
 * not an identity. `null` when nobody has rated him, which is not zero: a
 * new driver and a bad driver must never render alike.
 *
 * A name and a badge is what a decision actually needs - a driver confirming
 * a seat is asking "has this person been verified", not "what is their
 * number". **No contact details, in either direction.** Whether a confirmed
 * booking should unlock a phone number so the two can arrange a pickup is a
 * real product question and a privacy decision; it is not a field to bolt on
 * here, and nothing in the API answers it yet.
 *
 * @mixin User
 */
class RiderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $rider */
        $rider = $this->resource;

        return [
            'id' => $this->id,
            'name' => $rider->full_name,
            'verification_status' => $rider->verification_status->name,
            'verification_status_label' => $rider->verification_status->getLabel(),
            'rating' => [
                'average' => $rider->rating_average,
                'count' => $rider->ratings_count,
            ],
        ];
    }
}
