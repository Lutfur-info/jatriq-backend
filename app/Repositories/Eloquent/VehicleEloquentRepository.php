<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Models\Vehicle;
use App\Repositories\Contracts\VehicleRepository;

/**
 * @phpstan-import-type VehicleAttributes from VehicleRepository
 */
class VehicleEloquentRepository implements VehicleRepository
{
    /**
     * {@inheritDoc}
     */
    public function forUser(User $user): ?Vehicle
    {
        return $user->vehicle()->first();
    }

    /**
     * {@inheritDoc}
     */
    public function put(User $user, array $attributes): Vehicle
    {
        $vehicle = $this->forUser($user) ?? $user->vehicle()->make();

        $vehicle->fill($attributes);
        $vehicle->user()->associate($user);
        $vehicle->save();

        return $vehicle;
    }
}
