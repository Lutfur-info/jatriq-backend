<?php

namespace App\Models;

use Database\Factories\RideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A ride a driver is offering: where it starts, where it ends, when it leaves
 * and what one seat on it costs.
 *
 * The seats sold here are passenger seats, so `seats_offered` is bounded by
 * the vehicle's own `seats` and neither number counts the driver.
 *
 * `user_id` and `vehicle_id` are absent from the fillable list: the
 * repository associates both, so no request body can post a ride into
 * somebody else's name or car.
 *
 * The trip runs between two stops on one corridor. The driver picks the two
 * stops; `travel_route_id` and both sequences are resolved by RideService and
 * are likewise not fillable, so a body cannot claim a corridor the two stops
 * do not share. The label beside each end is a **snapshot** of that stop
 * taken at publish time, so renaming a stop never rewrites a trip somebody
 * already agreed to. The Google place id that used to sit beside it went on
 * 2026-09-19, with the one on the stop it was copied from.
 *
 * Direction is not a column: sequence runs outbound from Dhaka on every
 * route, so a ride toward Dhaka is one whose `destination_sequence` is below
 * its `origin_sequence`.
 *
 * @property int $id
 * @property int $user_id
 * @property int $vehicle_id
 * @property int|null $travel_route_id
 * @property string $origin_label
 * @property int|null $origin_stop_id
 * @property int|null $origin_sequence
 * @property string $destination_label
 * @property int|null $destination_stop_id
 * @property int|null $destination_sequence
 * @property Carbon $departs_at
 * @property numeric-string $seat_price
 * @property int $seats_offered
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Vehicle $vehicle
 * @property-read TravelRoute|null $travelRoute
 * @property-read Stop|null $originStop
 * @property-read Stop|null $destinationStop
 */
#[Fillable([
    'origin_label',
    'destination_label',
    'departs_at',
    'seat_price',
    'seats_offered',
])]
class Ride extends Model
{
    /** @use HasFactory<RideFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * The fare stays a fixed-point string, so a taka amount is never handed
     * around as a binary float.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departs_at' => 'datetime',
            'seat_price' => 'decimal:2',
            'seats_offered' => 'integer',
            'origin_sequence' => 'integer',
            'destination_sequence' => 'integer',
        ];
    }

    /**
     * The corridor the trip runs on.
     *
     * @return BelongsTo<TravelRoute, $this>
     */
    public function travelRoute(): BelongsTo
    {
        return $this->belongsTo(TravelRoute::class);
    }

    /**
     * The stop the vehicle leaves from.
     *
     * @return BelongsTo<Stop, $this>
     */
    public function originStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'origin_stop_id');
    }

    /**
     * The stop the vehicle finishes at.
     *
     * @return BelongsTo<Stop, $this>
     */
    public function destinationStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }

    /**
     * The driver offering the ride.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The seats passengers have taken, at most one booking per passenger.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The vehicle the seats are in.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
