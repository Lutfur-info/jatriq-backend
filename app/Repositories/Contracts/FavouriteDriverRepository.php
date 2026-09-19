<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The drivers a passenger has kept.
 *
 * Both arguments are always users, and their order is the whole meaning:
 * the first is the passenger doing the favouriting, the second the driver
 * favourited. Passing them the other way round would silently build the
 * mirror list.
 */
interface FavouriteDriverRepository
{
    /**
     * The passenger's favourite drivers, most recently added first.
     *
     * Each carries their vehicle, because that is what the list shows
     * beside the name.
     *
     * @return Collection<int, User>
     */
    public function forUser(User $passenger): Collection;

    /**
     * Whether the passenger has already kept this driver.
     */
    public function has(User $passenger, User $driver): bool;

    /**
     * Keep the driver, or leave the existing row exactly as it was.
     *
     * `syncWithoutDetaching` rather than `attach`: favouriting twice is the
     * same favourite, so a repeated tap is a no-op instead of a unique-key
     * violation - and it must not move the timestamp, or the list reorders
     * itself under a passenger who tapped a heart that was already filled.
     */
    public function add(User $passenger, User $driver): void;

    /**
     * Drop the driver from the list. Removing one that is not there is a
     * no-op, so the endpoint is idempotent from either direction.
     */
    public function remove(User $passenger, User $driver): void;
}
