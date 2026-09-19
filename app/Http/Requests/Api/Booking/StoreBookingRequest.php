<?php

namespace App\Http\Requests\Api\Booking;

use App\Models\Ride;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A passenger taking seats on a ride.
 *
 * The seat count is the whole request: the route, the departure and the fare
 * belong to the driver, and are settled from the moment the first seat sells.
 *
 * The cap here is the ride's published seats. How many are still *free* is
 * BookingService's call, because counting them and taking them has to happen
 * as one locked step.
 */
class StoreBookingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'seats' => ['required', 'integer', 'min:1', 'max:'.$this->ride()->seats_offered],
        ];
    }

    /**
     * The ride being booked, resolved from the route.
     */
    public function ride(): Ride
    {
        /** @var Ride $ride */
        $ride = $this->route('ride');

        return $ride;
    }

    /**
     * How many seats the passenger asked for.
     */
    public function seats(): int
    {
        return $this->integer('seats');
    }

    /**
     * Messages worth spelling out.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $offered = $this->ride()->seats_offered;

        return [
            'seats.max' => "This ride only has {$offered} passenger seats.",
        ];
    }
}
