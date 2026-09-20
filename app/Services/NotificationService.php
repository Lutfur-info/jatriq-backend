<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\NotificationRepository;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * What a user has been told, and marking it seen.
 *
 * Reading only. Nothing here *sends* a notification: the thing that happened
 * is what sends it, so a driver's answer goes out from `BookingService`
 * beside the decision it is about. A service whose job was "send
 * notifications" would have to know about every event in the system.
 *
 * The rules are thin on purpose - a feed is not a place for business logic -
 * but the scoping is not: every read is the caller's own, and "somebody
 * else's" is indistinguishable from "does not exist".
 */
class NotificationService
{
    public function __construct(private NotificationRepository $notifications) {}

    /**
     * Their feed, newest first.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function listFor(User $user): Collection
    {
        return $this->notifications->forUser($user);
    }

    /**
     * The number on the bell.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->notifications->unreadCountFor($user);
    }

    /**
     * Mark one read, or null when it is not theirs.
     *
     * The null is what the controller turns into a 404, the same answer a
     * ride that is not yours gives.
     */
    public function markRead(User $user, string $id): ?DatabaseNotification
    {
        $notification = $this->notifications->findFor($user, $id);

        if ($notification === null) {
            return null;
        }

        return $this->notifications->markRead($notification);
    }

    /**
     * Clear the bell, and report how many that was.
     */
    public function markAllRead(User $user): int
    {
        return $this->notifications->markAllReadFor($user);
    }
}
