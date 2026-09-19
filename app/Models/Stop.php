<?php

namespace App\Models;

use Database\Factories\StopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A named point on the map a vehicle can be boarded or left at.
 *
 * One row per place in the country, shared by every corridor passing through
 * it rather than owned by one: Cumilla is on the Chattogram, Noakhali and
 * Cox's Bazar runs at three different positions, and it has to be the same
 * stop in all three or a search from Cumilla would find only one corridor's
 * rides.
 *
 * Matching is done on the `sequence` the pivot carries - never on distance.
 * There is nothing here that points at a map: the coordinates went on
 * 2026-09-18 and the Google `place_id` on 2026-09-19, neither having ever
 * been read by anything. A town is a name, a district and a switch.
 *
 * The district is a **row, not a string** (2026-09-19). It was free text, so
 * nothing stopped the same district being spelled three ways; it is picked
 * now, and still only so two same-named towns read apart in a picker.
 *
 * @property int $id
 * @property string $name
 * @property int|null $district_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read District|null $district
 * @property-read int|null $sequence Present only when read through a route's stops.
 */
#[Fillable([
    'name',
    'district_id',
    'is_active',
])]
class Stop extends Model
{
    /** @use HasFactory<StopFactory> */
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
     * The district the town is in, if anybody has said which.
     *
     * Nullable, and deliberately so - a town is usable the moment it has a
     * name, and the district is only a label. `nullOnDelete` on the column
     * means losing a district never takes a town off the network.
     *
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * The corridors this stop sits on, each with its position on that one.
     *
     * @return BelongsToMany<TravelRoute, $this>
     */
    public function travelRoutes(): BelongsToMany
    {
        // The pivot is named for the road, not for Laravel's alphabetical
        // convention (which would make it `stop_travel_route`), so both
        // relations have to spell it out.
        return $this->belongsToMany(TravelRoute::class, 'travel_route_stop')
            ->withPivot('sequence')
            ->withTimestamps();
    }
}
