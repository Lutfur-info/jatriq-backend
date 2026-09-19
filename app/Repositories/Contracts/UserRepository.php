<?php

namespace App\Repositories\Contracts;

use App\enum\VerificationStatus;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * There is no read method here for the review queue: the admin panel is a
 * Filament resource, and a Filament table builds its own Eloquent query from
 * `UserResource::getEloquentQuery()`. Reads for the panel belong there; this
 * contract exists for the writes a service performs.
 */
interface UserRepository
{
    /**
     * Write a recomputed badge onto the user.
     *
     * Never mass assigned: the badge is derived from reviewed documents and
     * must not be reachable from a request body.
     */
    public function updateVerification(
        User $user,
        VerificationStatus $status,
        ?CarbonInterface $verifiedAt,
    ): User;

    /**
     * Write a recomputed driver score onto the user.
     *
     * Never mass assigned, for the same reason the badge is not: both are
     * derived - `RatingService::refreshFor()` is the only caller - and
     * neither may be reachable from a request body.
     *
     * A null average means nobody has rated him, which is not zero.
     */
    public function putRating(User $driver, ?float $average, int $count): User;
}
