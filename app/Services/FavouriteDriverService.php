<?php

namespace App\Services;

use App\enum\Role;
use App\Models\User;
use App\Repositories\Contracts\FavouriteDriverRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The drivers a passenger wants to ride with again.
 *
 * A favourite is a private note a passenger keeps. It grants nothing - it
 * does not reserve a seat, does not jump a queue, and is never shown to the
 * driver - so there is no locking here and no seat arithmetic to protect.
 *
 * The one rule worth a service rather than a controller: **only a Driver can
 * be favourited.** `role:Passenger` already says who may call, but nothing
 * about the route says who may be named in the body, and a passenger
 * favouriting another passenger would put somebody with no vehicle in a list
 * whose whole purpose is finding their next ride.
 */
class FavouriteDriverService
{
    public function __construct(private FavouriteDriverRepository $favourites) {}

    /**
     * The passenger's kept drivers, most recently added first.
     *
     * @return Collection<int, User>
     */
    public function listFor(User $passenger): Collection
    {
        return $this->favourites->forUser($passenger);
    }

    /**
     * Keep a driver.
     *
     * Idempotent: favouriting one already kept answers the same way and
     * leaves the row - and its timestamp - alone, so the list does not
     * reorder itself under a passenger who tapped a filled heart. The return
     * value says whether this call is what added it.
     *
     * @throws ValidationException when the id is not a driver's.
     */
    public function add(User $passenger, User $driver): bool
    {
        $this->refuseNonDriver($driver);

        if ($this->favourites->has($passenger, $driver)) {
            return false;
        }

        $this->favourites->add($passenger, $driver);

        return true;
    }

    /**
     * Drop a driver from the list.
     *
     * Idempotent in the same way: removing one that was never there is not
     * an error, so a client whose response went missing can simply repeat.
     */
    public function remove(User $passenger, User $driver): void
    {
        $this->favourites->remove($passenger, $driver);
    }

    /**
     * Whether the passenger is already keeping this driver.
     */
    public function has(User $passenger, User $driver): bool
    {
        return $this->favourites->has($passenger, $driver);
    }

    /**
     * Refuse anybody who is not a driver.
     *
     * A 422 keyed on the field rather than a 404, like every other refusal
     * in this API: the row exists, it is simply not something to favourite.
     *
     * @throws ValidationException
     */
    private function refuseNonDriver(User $driver): void
    {
        if ($driver->role !== Role::Driver) {
            throw ValidationException::withMessages([
                'driver_id' => 'You can only keep a driver as a favourite.',
            ]);
        }
    }
}
