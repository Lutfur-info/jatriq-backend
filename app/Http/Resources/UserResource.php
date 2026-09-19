<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'dial_code' => $this->dial_code,
            'msisdn' => $this->msisdn,
            'msisdn_verified' => $this->hasVerifiedMsisdn(),
            'gender' => $this->gender->name,
            'date_of_birth' => $this->date_of_birth,
            'role' => $this->role->name,
            'is_active' => $this->is_active,
            'verification_status' => $this->verification_status->name,
            'verified' => $this->isVerified(),
            'verified_at' => $this->verified_at,
            // A driver's own score, as passengers have left it. Null until
            // somebody rates him, which is not the same as zero. Present on
            // a passenger too, where it is simply never moved off null -
            // only a ride's own driver can be rated.
            'rating' => [
                'average' => $this->rating_average,
                'count' => $this->ratings_count,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
