<?php

namespace App\Repositories\Contracts;

use App\Models\Stop;

/**
 * The named points a vehicle can be boarded or left at.
 *
 * There is no list method: a bare catalogue of towns is not useful on its
 * own, because what a picker needs is the towns *on one corridor, in order* -
 * and that is TravelRouteRepository::active().
 */
interface StopRepository
{
    /**
     * One stop, or null when the id is unknown.
     */
    public function find(int $id): ?Stop;
}
