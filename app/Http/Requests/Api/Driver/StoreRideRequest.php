<?php

namespace App\Http\Requests\Api\Driver;

use App\Http\Requests\Concerns\BuildsRideRules;
use App\Models\User;
use App\Repositories\Contracts\VehicleRepository;
use App\Services\RideService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The ride a driver is offering: the two stops it runs between, when it
 * leaves, and what one passenger seat costs.
 *
 * @phpstan-import-type RideDetails from RideService
 */
class StoreRideRequest extends FormRequest
{
    use BuildsRideRules;

    /**
     * The vehicle answers how many seats there are to sell, so the cap is a
     * rule here rather than a check after the fact.
     */
    public function __construct(private VehicleRepository $vehicles)
    {
        parent::__construct();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        /*
         * A driver with no vehicle registered is refused by RideService, not
         * here - there is no field to hang that on. Until then the global
         * ceiling stands in, so the seat count is still bounded.
         */
        $vehicle = $this->vehicles->forUser($user);

        $seatsMax = $vehicle === null
            ? (int) config('vehicles.seats.max')
            : $vehicle->seats;

        return $this->rideRules($seatsMax);
    }

    /**
     * The ride details, ready for the service.
     *
     * Only the two stop ids - which corridor they share, where each falls on
     * it, and the label to freeze onto the ride are all TravelRouteService's
     * to work out, because they are facts about the road rather than claims
     * the driver is making.
     *
     * @return RideDetails
     */
    public function attributesForRide(): array
    {
        return [
            'origin_stop_id' => $this->integer('origin_stop_id'),
            'destination_stop_id' => $this->integer('destination_stop_id'),

            'departs_at' => $this->date('departs_at') ?? $this->earliestDeparture(),

            // Kept as a fixed-point string so a taka amount never becomes a
            // binary float on the way to a decimal column.
            'seat_price' => $this->string('seat_price')->trim()->toString(),

            'seats_offered' => $this->integer('seats_offered'),
        ];
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
