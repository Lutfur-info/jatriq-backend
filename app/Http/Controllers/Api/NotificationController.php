<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a rider has been told while they were not looking.
 *
 * Open to a driver and a passenger alike, like emergency contacts: both ends
 * of a ride can be told something. The first thing that lands here is a
 * driver's answer to a request for seats - a seat is asked for, not taken,
 * so the passenger has been waiting on exactly that.
 *
 * No `verified.identity` on any of it, for the reason her own bookings are
 * open: reading what you have already been told is not doing anything.
 *
 * Every response carries `unread_count` beside whatever else it returns, so
 * the bell on the client never needs a second call to stay in step - that is
 * the number the badge draws, and a mark-read that did not report it would
 * leave the badge lying until the next full read.
 */
class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Their feed, newest first, read and unread together.
     *
     * Read ones stay: "the driver declined this, and I saw that" is still
     * the record of what happened, and a feed that emptied itself on being
     * opened would be a worse place to look than the bookings list.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $this->notifications->listFor($user);

        return response()->json([
            'message' => $notifications->isEmpty()
                ? 'Nothing to catch up on.'
                : 'Your notifications.',
            'data' => [
                'notifications' => NotificationResource::collection($notifications),
                'unread_count' => $this->notifications->unreadCountFor($user),
            ],
        ]);
    }

    /**
     * Mark one read.
     *
     * A 404 for anybody else's, which is also the answer for a uuid that
     * does not exist - whether it does is not the caller's business when it
     * was not addressed to them.
     *
     * Idempotent: marking one that is already read answers the same way and
     * leaves `read_at` alone, so a lost response is safe to repeat.
     */
    public function update(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $read = $this->notifications->markRead($user, $notification);

        abort_if($read === null, 404);

        return response()->json([
            'message' => 'Marked as read.',
            'data' => [
                'notification' => new NotificationResource($read),
                'unread_count' => $this->notifications->unreadCountFor($user),
            ],
        ]);
    }

    /**
     * Clear the bell in one go.
     *
     * Reports how many it actually marked, which is zero when there was
     * nothing - not an error, for the same reason removing a favourite that
     * was never there is not one.
     */
    public function readAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $marked = $this->notifications->markAllRead($user);

        return response()->json([
            'message' => $marked === 0
                ? 'Nothing was waiting.'
                : 'All caught up.',
            'data' => [
                'marked_read' => $marked,
                'unread_count' => 0,
            ],
        ]);
    }
}
