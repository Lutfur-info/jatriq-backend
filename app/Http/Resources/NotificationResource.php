<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Deliberately **generic**: a feed holds more than one kind of thing,
     * and a resource that named a booking's fields would have to grow a
     * branch per notification class. So the stored payload is passed through
     * whole as `data`, and only the three things every notification has -
     * what kind it is, and the two lines a row shows - are hoisted beside it.
     *
     * `title` and `body` are **the server's wording**, for the reason
     * `status_label` is on a booking: a phone in somebody's pocket must not
     * need a release to reword a sentence. A client renders these, and reads
     * `data` only for what it wants to do next - `data.status` to colour the
     * row, `data.booking_id` to open the thing being talked about.
     *
     * `is_read` is derived rather than asked of the client, because "has a
     * `read_at`" is a rule and not a fact about presentation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->resource;

        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->getKey(),
            'type' => $data['type'] ?? 'notification',
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'data' => $data,
            'read_at' => $notification->read_at,
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at,
        ];
    }
}
