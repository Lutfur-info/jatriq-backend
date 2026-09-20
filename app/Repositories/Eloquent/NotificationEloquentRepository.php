<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepository;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationEloquentRepository implements NotificationRepository
{
    /**
     * {@inheritDoc}
     */
    public function forUser(User $user, int $limit = 50): Collection
    {
        return $user->notifications()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function unreadCountFor(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * {@inheritDoc}
     */
    public function findFor(User $user, string $id): ?DatabaseNotification
    {
        /*
         * Read off the relation rather than the table, so "not hers" and
         * "does not exist" come back as the same null. A `where('id', ...)`
         * on DatabaseNotification with an ownership check afterwards would
         * be the same query with one more chance to forget the check.
         */
        return $user->notifications()->whereKey($id)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function markRead(DatabaseNotification $notification): DatabaseNotification
    {
        // Laravel's own `markAsRead` already leaves a read row alone; this
        // says so at the seam rather than relying on reading that method.
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => Carbon::now()])->save();
        }

        return $notification;
    }

    /**
     * {@inheritDoc}
     */
    public function markAllReadFor(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => Carbon::now()]);
    }
}
