<?php

namespace App\Repositories\Eloquent;

use App\enum\VerificationStatus;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Carbon\CarbonInterface;

class UserEloquentRepository implements UserRepository
{
    /**
     * {@inheritDoc}
     */
    public function updateVerification(
        User $user,
        VerificationStatus $status,
        ?CarbonInterface $verifiedAt,
    ): User {
        $user->forceFill([
            'verification_status' => $status,
            'verified_at' => $verifiedAt,
        ])->save();

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function putRating(User $driver, ?float $average, int $count): User
    {
        $driver->forceFill([
            'rating_average' => $average,
            'ratings_count' => $count,
        ])->save();

        return $driver;
    }
}
