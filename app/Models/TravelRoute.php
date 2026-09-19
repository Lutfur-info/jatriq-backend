<?php

namespace App\Models;

use Database\Factories\TravelRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One of the highway corridors out of Dhaka, as an ordered list of stops.
 *
 * Corridors, not divisions. The Dhaka - Noakhali branch carries Laksam and
 * Sonaimuri and is not the Dhaka - Chattogram division highway, so a route
 * set of "one per division" cannot express a Sonaimuri ride at all.
 *
 * **Sequence runs outbound from Dhaka** on every route, so Dhaka always holds
 * the lowest number. A ride toward Dhaka is therefore one whose
 * `destination_sequence` is below its `origin_sequence`, and that is the
 * whole of how direction is represented - there is no direction column.
 *
 * Named `TravelRoute` rather than `Route` so it never collides with
 * Illuminate\Support\Facades\Route. The API still calls it a route.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Stop> $stops
 * @property-read Collection<int, Ride> $rides
 */
#[Fillable([
    'name',
    'slug',
    'is_active',
])]
class TravelRoute extends Model
{
    /** @use HasFactory<TravelRouteFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The stops along the corridor, ordered outbound from Dhaka.
     *
     * The ordering is part of the relation rather than left to each caller:
     * an unordered list of stops is meaningless here, and a picker rendering
     * them out of order would read as a different road.
     *
     * @return BelongsToMany<Stop, $this>
     */
    public function stops(): BelongsToMany
    {
        // The pivot is named for the road, not for Laravel's alphabetical
        // convention (which would make it `stop_travel_route`).
        return $this->belongsToMany(Stop::class, 'travel_route_stop')
            ->withPivot('sequence')
            ->withTimestamps()
            ->orderBy('travel_route_stop.sequence');
    }

    /**
     * The rides published on this corridor.
     *
     * @return HasMany<Ride, $this>
     */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }
}
