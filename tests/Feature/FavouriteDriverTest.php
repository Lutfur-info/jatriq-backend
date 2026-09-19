<?php

use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use Symfony\Component\HttpFoundation\Response;

/*
 * The drivers a passenger wants to ride with again.
 *
 * A private note she keeps: it reserves no seat, jumps no queue, and the
 * driver is never told. Keeping one needs the identity badge, like everything
 * else a passenger *does*; reading the list does not.
 */
beforeEach(function () {
    $this->passenger = User::factory()->passenger()->verified()->create();

    $this->driver = User::factory()->driver()->verified()->create(['first_name' => 'Rashed']);
    Vehicle::factory()->for($this->driver)->create(['registration_number' => 'DHAKA METRO-GA-77-4242']);

    $this->otherDriver = User::factory()->driver()->verified()->create(['first_name' => 'Jamal']);
    Vehicle::factory()->for($this->otherDriver)->create();
});

it('keeps a driver', function () {
    $this->actingAs($this->passenger)
        ->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id])
        ->assertStatus(Response::HTTP_CREATED)
        ->assertJsonPath('message', 'Driver added to your favourites.')
        ->assertJsonPath('data.driver.id', $this->driver->id)
        ->assertJsonPath('data.driver.name', $this->driver->full_name)
        ->assertJsonPath('data.driver.verification_status', 'Verified')
        // The car is what a passenger recognises, so the list carries it.
        ->assertJsonPath('data.driver.vehicle.registration_number', 'DHAKA METRO-GA-77-4242');

    expect($this->passenger->favouriteDrivers()->count())->toBe(1);
});

it('lists her favourites, most recently kept first', function () {
    $this->actingAs($this->passenger);

    $this->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id])
        ->assertStatus(Response::HTTP_CREATED);

    $this->travel(1)->minute();

    $this->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->otherDriver->id])
        ->assertStatus(Response::HTTP_CREATED);

    $this->getJson(route('api.favourite-drivers.index'))
        ->assertOk()
        ->assertJsonPath('message', 'Your favourite drivers.')
        ->assertJsonCount(2, 'data.drivers')
        ->assertJsonPath('data.drivers.0.id', $this->otherDriver->id)
        ->assertJsonPath('data.drivers.1.id', $this->driver->id)
        // No contact details, in either direction.
        ->assertJsonMissingPath('data.drivers.0.msisdn')
        ->assertJsonMissingPath('data.drivers.0.email');
});

it('answers an empty list before she has kept anybody', function () {
    $this->actingAs($this->passenger)
        ->getJson(route('api.favourite-drivers.index'))
        ->assertOk()
        ->assertJsonPath('message', 'No favourite drivers yet.')
        ->assertJsonCount(0, 'data.drivers');
});

it('treats favouriting the same driver twice as the same favourite', function () {
    $this->actingAs($this->passenger);

    $this->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id])
        ->assertStatus(Response::HTTP_CREATED);

    // A repeated tap is a no-op, not a unique-key violation - and it answers
    // 200 rather than 201, the same shape a repeat booking uses.
    $this->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id])
        ->assertOk()
        ->assertJsonPath('message', 'That driver is already in your favourites.');

    expect($this->passenger->favouriteDrivers()->count())->toBe(1);
});

it('does not reorder the list when she taps a heart that is already filled', function () {
    $this->actingAs($this->passenger);

    $this->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id]);
    $this->travel(1)->minute();
    $this->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->otherDriver->id]);

    $this->travel(1)->minute();

    // `syncWithoutDetaching` leaves the timestamp alone, so the older
    // favourite does not jump to the top of her list.
    $this->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id])
        ->assertOk();

    $this->getJson(route('api.favourite-drivers.index'))
        ->assertOk()
        ->assertJsonPath('data.drivers.0.id', $this->otherDriver->id)
        ->assertJsonPath('data.drivers.1.id', $this->driver->id);
});

it('removes a driver, and removing one twice is not an error', function () {
    $this->actingAs($this->passenger);

    $this->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id]);

    $this->deleteJson(route('api.favourite-drivers.destroy', ['driver' => $this->driver]))
        ->assertOk()
        ->assertJsonPath('message', 'Driver removed from your favourites.');

    expect($this->passenger->favouriteDrivers()->count())->toBe(0);

    // Idempotent, so a client whose response went missing simply repeats.
    $this->deleteJson(route('api.favourite-drivers.destroy', ['driver' => $this->driver]))
        ->assertOk();
});

it('keeps one passenger\'s favourites out of another\'s list', function () {
    $other = User::factory()->passenger()->verified()->create();

    $this->actingAs($this->passenger)
        ->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id])
        ->assertStatus(Response::HTTP_CREATED);

    $this->actingAs($other)
        ->getJson(route('api.favourite-drivers.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data.drivers');

    // Nor can she remove somebody else's.
    $this->actingAs($other)
        ->deleteJson(route('api.favourite-drivers.destroy', ['driver' => $this->driver]))
        ->assertOk();

    expect($this->passenger->favouriteDrivers()->count())->toBe(1);
});

it('refuses to favourite somebody who is not a driver', function (string $state) {
    $notADriver = User::factory()->{$state}()->create();

    $this->actingAs($this->passenger)
        ->postJson(route('api.favourite-drivers.store'), ['driver_id' => $notADriver->id])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors('driver_id');

    expect($this->passenger->favouriteDrivers()->count())->toBe(0);
})->with(['passenger', 'admin']);

it('refuses an account that does not exist', function () {
    $this->actingAs($this->passenger)
        ->postJson(route('api.favourite-drivers.store'), ['driver_id' => 9999])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors('driver_id');
});

it('demands the identity badge to keep or drop one, but not to look', function () {
    $unverified = User::factory()->passenger()->create();

    // Looking is open, the same bargain her bookings make.
    $this->actingAs($unverified)
        ->getJson(route('api.favourite-drivers.index'))
        ->assertOk();

    $this->actingAs($unverified)
        ->postJson(route('api.favourite-drivers.store'), ['driver_id' => $this->driver->id])
        ->assertStatus(Response::HTTP_FORBIDDEN)
        ->assertJsonPath('data.verification_status', 'Unverified');

    $this->actingAs($unverified)
        ->deleteJson(route('api.favourite-drivers.destroy', ['driver' => $this->driver]))
        ->assertStatus(Response::HTTP_FORBIDDEN);

    expect($unverified->favouriteDrivers()->count())->toBe(0);
});

it('keeps drivers and admins out of the favourites list entirely', function (string $state) {
    // `role:Passenger`: a favourite is a passenger's private note, and a
    // driver keeping a list of drivers is not a thing this product has.
    $this->actingAs(User::factory()->{$state}()->create())
        ->getJson(route('api.favourite-drivers.index'))
        ->assertStatus(Response::HTTP_FORBIDDEN);
})->with(['driver', 'admin']);

it('shows her who drove, so she has somebody to favourite', function () {
    $ride = Ride::factory()->forDriver($this->driver)->create(['seats_offered' => 4]);

    $this->actingAs($this->passenger)
        ->postJson(route('api.rides.bookings.store', ['ride' => $ride]), ['seats' => 1])
        ->assertStatus(Response::HTTP_CREATED);

    // Nothing else a passenger can open names a driver - the public board
    // deliberately does not - so without this there is nothing to favourite.
    $this->actingAs($this->passenger)
        ->getJson(route('api.bookings.index'))
        ->assertOk()
        ->assertJsonPath('data.bookings.0.driver.id', $this->driver->id)
        ->assertJsonPath('data.bookings.0.driver.name', 'Rashed '.$this->driver->last_name)
        ->assertJsonMissingPath('data.bookings.0.driver.msisdn');
});

it('still keeps the driver off the public board', function () {
    Ride::factory()->forDriver($this->driver)->create(['seats_offered' => 4]);

    // GET /api/rides has no token, so a name there is a name on the open
    // internet. Naming the driver on a booking she made does not change it.
    $this->getJson(route('api.rides.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.rides')
        ->assertJsonMissingPath('data.rides.0.driver')
        ->assertJsonMissingPath('data.rides.0.user');
});
