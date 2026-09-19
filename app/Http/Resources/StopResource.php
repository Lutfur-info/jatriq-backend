<?php

namespace App\Http\Resources;

use App\Models\Stop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Stop
 */
class StopResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `district` stays a plain string in the response although it is a row
     * now (2026-09-19), so no client had to change. Load the relation or
     * every stop in a corridor costs a query.
     *
     * `sequence` comes off the pivot and is therefore only present when the
     * stop is read through a corridor - which is the only way the API serves
     * one. It is what puts the towns in order in a picker, and a client must
     * never sort by name instead.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Stop $stop */
        $stop = $this->resource;

        $sequence = $stop->getAttribute('pivot')?->getAttribute('sequence');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'district' => $stop->district?->name,
            'sequence' => is_numeric($sequence) ? (int) $sequence : null,
        ];
    }
}
