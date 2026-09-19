<?php

namespace App\Http\Requests\Api\Driver;

use App\Http\Requests\Concerns\BuildsRideRules;
use App\Models\Ride;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A change to a ride nobody has booked yet.
 *
 * Partial, like an emergency contact edit: only the fields sent are written,
 * so correcting the fare leaves both ends of the trip exactly as they were.
 * Whether the ride may be edited at all is RideService's call, not a rule
 * here - it depends on bookings, not on the body.
 */
class UpdateRideRequest extends FormRequest
{
    use BuildsRideRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // The ride's own vehicle, not the driver's current one: these seats
        // are the ones the ride was published against.
        return $this->rideRules($this->ride()->vehicle->seats, partial: true);
    }

    /**
     * The ride being edited, resolved from the route.
     */
    public function ride(): Ride
    {
        /** @var Ride $ride */
        $ride = $this->route('ride');

        return $ride;
    }

    /**
     * Refuse an edit that would leave the ride starting where it ends.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // The fields left out are read from the ride, so moving only
                // the destination is still checked against the real start.
                if ($this->startsWhereItEnds($this->ride())) {
                    $validator->errors()->add(
                        'destination_stop_id',
                        'The destination must be somewhere other than the start point.',
                    );
                }
            },
        ];
    }

    /**
     * Only the fields the request actually sent, so a partial edit is partial.
     *
     * @return array<string, mixed>
     */
    public function attributesForRide(): array
    {
        $attributes = [];

        if ($this->has('seat_price')) {
            $attributes['seat_price'] = $this->string('seat_price')->trim()->toString();
        }

        /*
         * Either end on its own is enough to move the ride: RideService reads
         * the other off the ride and re-resolves the corridor for the pair,
         * because moving one end can put the trip on a different road.
         */
        foreach (['origin_stop_id', 'destination_stop_id'] as $field) {
            if ($this->has($field)) {
                $attributes[$field] = $this->integer($field);
            }
        }

        if ($this->has('departs_at')) {
            $attributes['departs_at'] = $this->date('departs_at');
        }

        if ($this->has('seats_offered')) {
            $attributes['seats_offered'] = $this->integer('seats_offered');
        }

        return $attributes;
    }

    /**
     * Human readable field names for the validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->rideAttributeNames();
    }

    /**
     * Messages worth spelling out.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->rideMessages();
    }
}
