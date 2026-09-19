<?php

namespace App\Http\Resources;

use App\Models\EmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmergencyContact
 */
class EmergencyContactResource extends JsonResource
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
            'name' => $this->name,
            'relation' => $this->relation,
            'dial_code' => $this->dial_code,
            'msisdn' => $this->msisdn,
            'international_msisdn' => $this->international_msisdn,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
