<?php

namespace App\Notifications;

use App\enum\BookingStatus;
use App\Models\Booking;
use Illuminate\Notifications\Notification;

/**
 * The driver has answered a passenger's request for seats.
 *
 * The one thing a passenger is genuinely waiting on. A seat is asked for,
 * not taken, so between booking and boarding there is a silence only this
 * ends - and she will not have the app open when it does.
 *
 * Stored, not pushed. The `database` channel is the whole delivery here:
 * there is no device token anywhere in this system and no SMS budget for it,
 * so the answer waits in her feed and the bell tells her it is there. Adding
 * a push channel later means adding it to `via()` and nothing else, which is
 * the reason this is a Notification rather than a row the service writes.
 *
 * The payload is a **copy**, not a set of ids to resolve later. A feed has
 * to render from what it holds: the ride can be edited out from under it,
 * and "your seat on ride 41 was confirmed" is unreadable to anybody who has
 * to look 41 up. Only `booking_id` and `ride_id` point outward, for a client
 * that wants to open the thing being talked about.
 */
class BookingDecided extends Notification
{
    public function __construct(private Booking $booking) {}

    /**
     * The channels this goes out on.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * What is stored, and what the API serves back.
     *
     * `title` and `body` are worded here rather than in each client, for the
     * reason `BookingStatus::getLabel()` exists: the wording is the server's,
     * and a phone in somebody's pocket must not have to ship a release to
     * reword a sentence. `status` rides along beside them so a client can
     * still colour the row without parsing English.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $ride = $this->booking->ride;
        $status = $this->booking->status;
        $confirmed = $status === BookingStatus::Confirmed;
        $seats = $this->booking->seats;

        return [
            'type' => 'booking_decided',
            'booking_id' => $this->booking->getKey(),
            'ride_id' => $ride->getKey(),
            'status' => $status->name,
            'status_label' => $status->getLabel(),
            'seats' => $seats,
            'origin_name' => $ride->origin_label,
            'destination_name' => $ride->destination_label,
            'departs_at' => $ride->departs_at->toJson(),
            'title' => $confirmed ? 'Your seat is confirmed' : 'Your request was declined',
            'body' => $confirmed
                ? "The driver confirmed your {$seats} ".($seats === 1 ? 'seat' : 'seats').
                    " on {$ride->origin_label} to {$ride->destination_label}."
                : "The driver declined your request for {$seats} ".($seats === 1 ? 'seat' : 'seats').
                    " on {$ride->origin_label} to {$ride->destination_label}. Those seats are back on sale.",
        ];
    }
}
