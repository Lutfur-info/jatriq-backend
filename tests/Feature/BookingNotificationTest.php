<?php

use App\enum\BookingStatus;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use App\Notifications\BookingDecided;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

/*
 * A seat is asked for, not taken, so between booking and boarding there is a
 * silence only the driver's answer ends - and the passenger will not have the
 * app open when it does. This is what tells her.
 *
 * Stored, not pushed: the notification waits in her feed and the bell counts
 * it. That is the whole delivery, and these pin both halves - that the answer
 * puts something there, and that only she can read or clear it.
 */
beforeEach(function () {
    $this->driver = User::factory()->driver()->verified()->create();
    $this->passenger = User::factory()->passenger()->verified()->create();

    $this->ride = Ride::factory()->forDriver($this->driver)->create(['seats_offered' => 4]);
});

/**
 * The passenger asks for seats, the way the API does.
 */
function ask_for_seats(User $passenger, Ride $ride, int $seats = 1): Booking
{
    test()->actingAs($passenger)
        ->postJson(route('api.rides.bookings.store', ['ride' => $ride]), ['seats' => $seats])
        ->assertStatus(Response::HTTP_CREATED);

    return Booking::query()->where('ride_id', $ride->id)->where('user_id', $passenger->id)->sole();
}

/**
 * The driver answers, the way the API does.
 */
function answer_request(User $driver, Booking $booking, BookingStatus $status): void
{
    test()->actingAs($driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking]), ['status' => $status->name])
        ->assertOk();
}

it('tells the passenger when the driver confirms her seat', function () {
    Notification::fake();

    $booking = ask_for_seats($this->passenger, $this->ride, 2);

    // Asking is not news to her - she is the one who asked.
    Notification::assertNothingSent();

    answer_request($this->driver, $booking, BookingStatus::Confirmed);

    Notification::assertSentTo(
        $this->passenger,
        BookingDecided::class,
        function (BookingDecided $notification) use ($booking) {
            $payload = $notification->toArray($this->passenger);

            return $payload['booking_id'] === $booking->id
                && $payload['status'] === 'Confirmed'
                && $payload['seats'] === 2
                && str_contains($payload['body'], '2 seats');
        },
    );

    // The driver is not told what they themselves just decided.
    Notification::assertNotSentTo($this->driver, BookingDecided::class);
});

it('tells her when it is declined, and says the seats went back', function () {
    Notification::fake();

    $booking = ask_for_seats($this->passenger, $this->ride, 1);

    answer_request($this->driver, $booking, BookingStatus::Declined);

    Notification::assertSentTo(
        $this->passenger,
        BookingDecided::class,
        function (BookingDecided $notification) {
            $payload = $notification->toArray($this->passenger);

            return $payload['status'] === 'Declined'
                && $payload['title'] === 'Your request was declined'
                && str_contains($payload['body'], 'back on sale');
        },
    );
});

it('does not tell her twice when the driver sends the same answer again', function () {
    Notification::fake();

    $booking = ask_for_seats($this->passenger, $this->ride, 1);

    answer_request($this->driver, $booking, BookingStatus::Confirmed);
    // Exactly what a client whose response went missing does.
    answer_request($this->driver, $booking->refresh(), BookingStatus::Confirmed);

    Notification::assertSentToTimes($this->passenger, BookingDecided::class, 1);
});

it('tells her again when the driver changes their mind', function () {
    Notification::fake();

    $booking = ask_for_seats($this->passenger, $this->ride, 1);

    answer_request($this->driver, $booking, BookingStatus::Confirmed);
    answer_request($this->driver, $booking->refresh(), BookingStatus::Declined);

    Notification::assertSentToTimes($this->passenger, BookingDecided::class, 2);
});

it('leaves nothing behind when the confirmation is refused', function () {
    Notification::fake();

    $booking = ask_for_seats($this->passenger, $this->ride, 4);

    answer_request($this->driver, $booking, BookingStatus::Declined);

    // Somebody else takes the seats that came back.
    $other = User::factory()->passenger()->verified()->create();
    ask_for_seats($other, $this->ride, 4);

    $this->actingAs($this->driver)
        ->patchJson(route('api.driver.bookings.update', ['booking' => $booking->refresh()]), [
            'status' => BookingStatus::Confirmed->name,
        ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

    // One notification, for the decline that did happen - and nothing
    // claiming a confirmation the API refused.
    Notification::assertSentToTimes($this->passenger, BookingDecided::class, 1);
});

it('lands the answer in her feed, with the wording the server chose', function () {
    $booking = ask_for_seats($this->passenger, $this->ride, 2);

    answer_request($this->driver, $booking, BookingStatus::Confirmed);

    $this->actingAs($this->passenger)
        ->getJson(route('api.notifications.index'))
        ->assertOk()
        ->assertJsonPath('message', 'Your notifications.')
        ->assertJsonCount(1, 'data.notifications')
        ->assertJsonPath('data.unread_count', 1)
        ->assertJsonPath('data.notifications.0.type', 'booking_decided')
        ->assertJsonPath('data.notifications.0.title', 'Your seat is confirmed')
        ->assertJsonPath('data.notifications.0.is_read', false)
        ->assertJsonPath('data.notifications.0.read_at', null)
        // The payload rides along whole, so a client can open what it is
        // about without a second call.
        ->assertJsonPath('data.notifications.0.data.booking_id', $booking->id)
        ->assertJsonPath('data.notifications.0.data.ride_id', $this->ride->id)
        ->assertJsonPath('data.notifications.0.data.status', 'Confirmed');
});

it('answers an empty feed without pretending something is waiting', function () {
    $this->actingAs($this->passenger)
        ->getJson(route('api.notifications.index'))
        ->assertOk()
        ->assertJsonPath('message', 'Nothing to catch up on.')
        ->assertJsonCount(0, 'data.notifications')
        ->assertJsonPath('data.unread_count', 0);
});

it('marks one read and reports the bell back in step', function () {
    $booking = ask_for_seats($this->passenger, $this->ride, 1);
    answer_request($this->driver, $booking, BookingStatus::Confirmed);

    $id = $this->passenger->notifications()->sole()->id;

    $this->actingAs($this->passenger)
        ->postJson(route('api.notifications.read', ['notification' => $id]))
        ->assertOk()
        ->assertJsonPath('data.notification.is_read', true)
        ->assertJsonPath('data.unread_count', 0);

    expect($this->passenger->fresh()->unreadNotifications()->count())->toBe(0);
});

it('accepts marking one read twice without moving when she saw it', function () {
    $booking = ask_for_seats($this->passenger, $this->ride, 1);
    answer_request($this->driver, $booking, BookingStatus::Confirmed);

    $id = $this->passenger->notifications()->sole()->id;

    $this->actingAs($this->passenger)
        ->postJson(route('api.notifications.read', ['notification' => $id]))
        ->assertOk();

    $seenAt = $this->passenger->notifications()->sole()->read_at;

    $this->travel(5)->minutes();

    $this->actingAs($this->passenger)
        ->postJson(route('api.notifications.read', ['notification' => $id]))
        ->assertOk();

    expect($this->passenger->notifications()->sole()->read_at->toJson())->toBe($seenAt->toJson());
});

it('refuses to let anybody mark somebody else notification read', function () {
    $booking = ask_for_seats($this->passenger, $this->ride, 1);
    answer_request($this->driver, $booking, BookingStatus::Confirmed);

    $id = $this->passenger->notifications()->sole()->id;

    // A 404, not a 403: whether that uuid exists is not his business.
    $this->actingAs($this->driver)
        ->postJson(route('api.notifications.read', ['notification' => $id]))
        ->assertNotFound();

    expect($this->passenger->fresh()->unreadNotifications()->count())->toBe(1);
});

it('never shows one rider what another was told', function () {
    $booking = ask_for_seats($this->passenger, $this->ride, 1);
    answer_request($this->driver, $booking, BookingStatus::Confirmed);

    $this->actingAs($this->driver)
        ->getJson(route('api.notifications.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data.notifications')
        ->assertJsonPath('data.unread_count', 0);
});

it('clears the whole bell in one call', function () {
    $booking = ask_for_seats($this->passenger, $this->ride, 1);
    answer_request($this->driver, $booking, BookingStatus::Confirmed);
    answer_request($this->driver, $booking->refresh(), BookingStatus::Declined);

    $this->actingAs($this->passenger)
        ->postJson(route('api.notifications.read-all'))
        ->assertOk()
        ->assertJsonPath('message', 'All caught up.')
        ->assertJsonPath('data.marked_read', 2)
        ->assertJsonPath('data.unread_count', 0);

    expect($this->passenger->fresh()->unreadNotifications()->count())->toBe(0);
});

it('treats clearing an empty bell as nothing to do', function () {
    $this->actingAs($this->passenger)
        ->postJson(route('api.notifications.read-all'))
        ->assertOk()
        ->assertJsonPath('message', 'Nothing was waiting.')
        ->assertJsonPath('data.marked_read', 0);
});

it('keeps the feed shut to anybody who is not signed in', function () {
    $this->getJson(route('api.notifications.index'))->assertUnauthorized();
    $this->postJson(route('api.notifications.read-all'))->assertUnauthorized();
});

it('refuses an admin, who does not ride', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson(route('api.notifications.index'))
        ->assertForbidden();
});
