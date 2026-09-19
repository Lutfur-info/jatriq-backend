<?php

namespace App\Http\Controllers\Api\Driver;

use App\enum\CabinClass;
use App\enum\VehicleModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\StoreVehicleRequest;
use App\Http\Resources\UserDocumentResource;
use App\Http\Resources\VehicleResource;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The vehicle a driver drives, and the licence that goes with it.
 *
 * Driver only, and the one place the two roles genuinely differ: a passenger
 * has no vehicle to describe. Identity documents stay on the shared
 * /verification endpoint; this is the vehicle, its plate and the licence.
 *
 * A driver has at most one vehicle, so there is no id in the URL and no list:
 * POST registers it the first time and replaces the details afterwards.
 */
class VehicleController extends Controller
{
    public function __construct(private VehicleService $vehicles) {}

    /**
     * Show the driver's vehicle, their licence, and the options to choose from.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $vehicle = $this->vehicles->forUser($user);

        return response()->json([
            'message' => $vehicle === null ? 'No vehicle registered yet.' : 'Vehicle details.',
            'data' => $this->payload($user, $vehicle),
        ]);
    }

    /**
     * Register the vehicle, or replace the details already held.
     */
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $licence = $request->licence();

        $vehicle = $this->vehicles->save($user, $request->attributesForVehicle(), $licence);

        return response()->json([
            'message' => $licence === null
                ? 'Vehicle details saved.'
                : 'Vehicle details saved. Your licence is awaiting review.',
            'data' => $this->payload($user, $vehicle),
        ]);
    }

    /**
     * The body both verbs return.
     *
     * The choices are served alongside the record so a client never hardcodes
     * an enum: a model added on the API turns up in the picker on its own.
     *
     * @return array<string, mixed>
     */
    private function payload(User $user, ?Vehicle $vehicle): array
    {
        $licence = $this->vehicles->licenceFor($user);

        return [
            'vehicle' => $vehicle === null ? null : new VehicleResource($vehicle),
            'driving_licence' => $licence === null ? null : new UserDocumentResource($licence),
            'options' => [
                'models' => array_map(
                    fn (VehicleModel $model): array => [
                        'value' => $model->name,
                        'label' => $model->label(),
                        'typical_seats' => $model->typicalSeats(),
                    ],
                    VehicleModel::cases(),
                ),
                'cabin_classes' => array_map(
                    fn (CabinClass $class): array => [
                        'value' => $class->name,
                        'label' => $class->label(),
                    ],
                    CabinClass::cases(),
                ),
                'seats' => [
                    'min' => (int) config('vehicles.seats.min'),
                    'max' => (int) config('vehicles.seats.max'),
                ],
            ],
        ];
    }
}
