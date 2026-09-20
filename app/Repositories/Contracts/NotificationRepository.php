<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * What a user has been told, and what they have not yet seen.
 *
 * The rows are Laravel's own `DatabaseNotification`, not a model of ours:
 * the payload shape is the notification class's business, and a feed renders
 * each row from its own `data`. This contract exists so a service never
 * reaches for `$user->notifications()` directly - the same bargain every
 * other subject here makes.
 *
 * **Every method is scoped to a user.** There is no "find by id" that is not
 * also "and it is theirs": a notification id is a uuid somebody could still
 * guess at, and marking somebody else's as read would be the whole
 * vulnerability.
 */
interface NotificationRepository
{
    /**
     * Their feed, newest first.
     *
     * Capped rather than paginated: this is a phone's notification list, and
     * [limit] rows is far more than anybody scrolls. Pagination is what to
     * add when it stops being true - the response shape changes then.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function forUser(User $user, int $limit = 50): Collection;

    /**
     * How many they have not opened. This is the number on the bell.
     */
    public function unreadCountFor(User $user): int;

    /**
     * One of theirs, or null - which the caller turns into a 404, because
     * whether a uuid exists is not the caller's business when it is not
     * addressed to them.
     */
    public function findFor(User $user, string $id): ?DatabaseNotification;

    /**
     * Mark it read, leaving one already read exactly as it was.
     *
     * Idempotent for the reason a favourite is: a lost response has to be
     * safe to repeat, and moving `read_at` on a second call would rewrite
     * when she saw it.
     */
    public function markRead(DatabaseNotification $notification): DatabaseNotification;

    /**
     * Mark everything unread as read, and report how many that was.
     */
    public function markAllReadFor(User $user): int;
}
