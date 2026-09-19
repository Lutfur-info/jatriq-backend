<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Both enums are sent as the case name the API accepts back, alongside a
     * label a client can render without a translation table of its own.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'model' => $this->model->name,
            'model_label' => $this->model->label(),
            'cabin_class' => $this->cabin_class->name,
            'cabin_class_label' => $this->cabin_class->label(),
            'seats' => $this->seats,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
