<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\FavouriteDriverRepository;
use Illuminate\Database\Eloquent\Collection;

class FavouriteDriverEloquentRepository implements FavouriteDriverRepository
{
    /**
     * {@inheritDoc}
     */
    public function forUser(User $passenger): Collection
    {
        return $passenger->favouriteDrivers()
            // FavouriteDriverResource prints the plate and the cabin class
            // beside the name, so the car comes along rather than one query
            // per driver.
            ->with('vehicle')
            ->orderByPivot('created_at', 'desc')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function has(User $passenger, User $driver): bool
    {
        return $passenger->favouriteDrivers()
            ->whereKey($driver->getKey())
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function add(User $passenger, User $driver): void
    {
        $passenger->favouriteDrivers()->syncWithoutDetaching([$driver->getKey()]);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(User $passenger, User $driver): void
    {
        $passenger->favouriteDrivers()->detach($driver->getKey());
    }
}
