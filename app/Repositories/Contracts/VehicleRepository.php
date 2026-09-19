<?php

namespace App\Repositories\Contracts;

use App\enum\CabinClass;
use App\enum\VehicleModel;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Persistence for the one vehicle a driver holds.
 *
 * @phpstan-type VehicleAttributes array{
 *     registration_number: string,
 *     model: VehicleModel,
 *     cabin_class: CabinClass,
 *     seats: int,
 * }
 */
interface VehicleRepository
{
    /**
     * The driver's vehicle, if they have registered one.
     */
    public function forUser(User $user): ?Vehicle;

    /**
     * Record the driver's vehicle, replacing the details already held.
     *
     * A driver has at most one, so this creates or overwrites rather than
     * appending - there is no id for a caller to choose between.
     *
     * @param  VehicleAttributes  $attributes
     */
    public function put(User $user, array $attributes): Vehicle;
}
