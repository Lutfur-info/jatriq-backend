<?php

use App\enum\BookingStatus;
use App\Filament\Resources\Rides\Pages\EditRide;
use App\Filament\Resources\Rides\Pages\ListRides;
use App\Filament\Resources\Rides\Pages\ViewRide;
use App\Filament\Resources\Rides\RelationManagers\BookingsRelationManager;
use App\Filament\Resources\Rides\RideResource;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/*
 * The panel's read of the board: every ride there is, and who is in each one.
 *
 *   Noakhali   Dhaka 10 - Cumilla 20 - Laksam 30
 *
 *   upcoming   Laksam -> Dhaka, tomorrow, 4 seats at 500, 3 of them sold
 *   full       Laksam -> Dhaka, tomorrow, 2 seats, both sold
 *   departed   Laksam -> Dhaka, yesterday, nobody booked
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->driver = User::factory()->driver()->verified()->create();

    // One vehicle per driver, and the relation is read once per ride the
    // factory builds - so load it, or each ride tries to register another.
    Vehicle::factory()->create(['user_id' => $this->driver->getKey(), 'seats' => 8]);
    $this->driver->load('vehicle');

    $this->dhaka = $dhaka = Stop::factory()->create(['name' => 'Dhaka']);
    $this->cumilla = $cumilla = Stop::factory()->create(['name' => 'Cumilla']);
    $this->laksam = $laksam = Stop::factory()->create(['name' => 'Laksam']);

    $this->noakhali = $noakhali = TravelRoute::factory()
        ->along($dhaka, $cumilla, $laksam)
        ->create(['name' => 'Dhaka - Noakhali']);

    $toDhaka = fn (Carbon $departsAt, int $seats): Ride => Ride::factory()
        ->forDriver($this->driver)
        ->between($noakhali, $laksam, $dhaka)
        ->departingAt($departsAt)
        ->create(['seat_price' => 500, 'seats_offered' => $seats]);

    $this->upcoming = $toDhaka(Carbon::now()->addDay(), 4);
    $this->full = $toDhaka(Carbon::now()->addDay(), 2);
    $this->departed = $toDhaka(Carbon::now()->subDay(), 4);

    $this->rumana = User::factory()->passenger()->create(['first_name' => 'Rumana']);

    $this->single = Booking::factory()->for($this->upcoming)->ofSeats(1)->create();
    $this->pair = Booking::factory()->for($this->upcoming)->for($this->rumana)->ofSeats(2)->create();

    Booking::factory()->for($this->full)->ofSeats(2)->create();
});

it('keeps riders away from the rides resource', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(RideResource::getUrl('index'))
        ->assertStatus(Response::HTTP_FORBIDDEN);
})->with(['driver', 'passenger']);

it('renders both rides pages for an admin', function () {
    $this->actingAs($this->admin);

    $this->get(RideResource::getUrl('index'))->assertOk()->assertSee('Laksam');
    $this->get(RideResource::getUrl('view', ['record' => $this->upcoming]))
        ->assertOk()
        ->assertSee('Dhaka - Noakhali')
        ->assertSee($this->driver->full_name);
});

it('lists every ride, the ones that have already left included', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListRides::class)
        ->assertCanSeeTableRecords([$this->upcoming, $this->full, $this->departed]);
});

it('counts the seats booked and the seats still free', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListRides::class)
        // Two bookings, of one seat and two, against the four offered.
        ->assertTableColumnStateSet('seats_booked', 3, $this->upcoming)
        ->assertTableColumnStateSet('seats_available', 1, $this->upcoming)
        ->assertTableColumnStateSet('seats_booked', 2, $this->full)
        ->assertTableColumnStateSet('seats_available', 0, $this->full)
        ->assertTableColumnStateSet('seats_booked', 0, $this->departed);
});

it('narrows the board to the rides that have not left yet', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListRides::class)
        ->filterTable('departure', true)
        ->assertCanSeeTableRecords([$this->upcoming, $this->full])
        ->assertCanNotSeeTableRecords([$this->departed]);
});

it('separates the rides with a seat left from the ones that are full', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListRides::class)
        ->filterTable('seats', true)
        ->assertCanSeeTableRecords([$this->upcoming, $this->departed])
        ->assertCanNotSeeTableRecords([$this->full]);

    Livewire::test(ListRides::class)
        ->filterTable('seats', false)
        ->assertCanSeeTableRecords([$this->full])
        ->assertCanNotSeeTableRecords([$this->upcoming, $this->departed]);
});

it('shows who is in the vehicle, and what each of them owes', function () {
    $this->actingAs($this->admin);

    Livewire::test(BookingsRelationManager::class, [
        'ownerRecord' => $this->upcoming,
        'pageClass' => ViewRide::class,
    ])
        ->assertCanSeeTableRecords([$this->single, $this->pair])
        ->assertTableColumnStateSet('seats', 2, $this->pair)
        // Two seats at the ride's own fare, fixed point throughout.
        ->assertTableColumnStateSet('total_amount', '1000.00', $this->pair)
        ->assertSee('Rumana');
});

it('shows only that ride\'s passengers', function () {
    $this->actingAs($this->admin);

    Livewire::test(BookingsRelationManager::class, [
        'ownerRecord' => $this->full,
        'pageClass' => ViewRide::class,
    ])->assertCanNotSeeTableRecords([$this->single, $this->pair]);
});

it('counts a pending request as held and a declined one as free', function () {
    $this->actingAs($this->admin);

    // The upcoming ride holds 3 of its 4 seats across two pending requests.
    // A decline gives seats back; nothing else does.
    Booking::factory()->for($this->departed)->ofSeats(2)->declined()->create();

    Livewire::test(ListRides::class)
        ->assertTableColumnStateSet('seats_booked', 3, $this->upcoming)
        ->assertTableColumnStateSet('pending_bookings_count', 2, $this->upcoming)
        // Declined, so the car is empty again and nobody is waiting.
        ->assertTableColumnStateSet('seats_booked', 0, $this->departed)
        ->assertTableColumnStateSet('pending_bookings_count', 0, $this->departed);
});

it('narrows the board to the rides with somebody waiting on the driver', function () {
    $this->actingAs($this->admin);

    Booking::factory()->for($this->departed)->ofSeats(1)->confirmed()->create();

    Livewire::test(ListRides::class)
        ->filterTable('waiting')
        ->assertCanSeeTableRecords([$this->upcoming, $this->full])
        // Answered, so it is off the queue.
        ->assertCanNotSeeTableRecords([$this->departed]);
});

it('shows the driver\'s answer beside each passenger', function () {
    $this->actingAs($this->admin);

    // `status` is absent from the model's fillable list on purpose - it is
    // the repository's to write, so no request body can confirm itself.
    $this->pair->status = BookingStatus::Confirmed;
    $this->pair->decided_at = now();
    $this->pair->save();

    Livewire::test(BookingsRelationManager::class, [
        'ownerRecord' => $this->upcoming,
        'pageClass' => ViewRide::class,
    ])
        ->assertTableColumnStateSet('status', BookingStatus::Confirmed, $this->pair)
        ->assertTableColumnStateSet('status', BookingStatus::Pending, $this->single)
        ->filterTable('status', BookingStatus::Pending->name)
        ->assertCanSeeTableRecords([$this->single])
        ->assertCanNotSeeTableRecords([$this->pair]);
});

it('badges the passenger tab with the seats sold', function () {
    expect(BookingsRelationManager::getBadge($this->upcoming, ViewRide::class))->toBe('3')
        ->and(BookingsRelationManager::getBadge($this->departed, ViewRide::class))->toBeNull();
});

it('creates and deletes nothing, but edits', function () {
    // A ride is published by a driver and a booking made by a passenger, so
    // there is nothing here to create; with no cancellation anywhere, there
    // is nothing to delete either. Correcting one is the exception.
    expect(RideResource::canCreate())->toBeFalse()
        ->and(array_keys(RideResource::getPages()))->toBe(['index', 'view', 'edit'])
        ->and(RideResource::getRelations())->toBe([BookingsRelationManager::class]);
});

it('corrects a ride nobody has booked', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditRide::class, ['record' => $this->departed->getKey()])
        ->fillForm([
            'origin_stop_id' => $this->cumilla->getKey(),
            'destination_stop_id' => $this->dhaka->getKey(),
            'departs_at' => Carbon::now()->addDays(2),
            'seat_price' => 650,
            'seats_offered' => 6,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->departed->refresh();

    expect($this->departed->origin_stop_id)->toBe($this->cumilla->getKey())
        // The label is re-snapshotted from the stop, and the corridor and
        // both sequences are resolved again rather than left where they were.
        ->and($this->departed->origin_label)->toBe('Cumilla')
        ->and($this->departed->travel_route_id)->toBe($this->noakhali->getKey())
        ->and($this->departed->origin_sequence)->toBe(20)
        ->and($this->departed->destination_sequence)->toBe(10)
        ->and($this->departed->seats_offered)->toBe(6);
});

it('corrects a ride that passengers are already on', function () {
    $this->actingAs($this->admin);

    // The driver's own edit is closed here - `RideService::change()` refuses
    // it outright - and with no cancellation this is the only way to fix it.
    Livewire::test(EditRide::class, ['record' => $this->upcoming->getKey()])
        ->fillForm(['departs_at' => Carbon::now()->addDays(3)->setSeconds(0)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->upcoming->refresh()->departs_at->isSameDay(Carbon::now()->addDays(3)))->toBeTrue()
        // The seats sold are untouched by the correction.
        ->and($this->upcoming->bookings()->sum('seats'))->toBe(3);
});

it('refuses to offer fewer seats than passengers already hold', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditRide::class, ['record' => $this->upcoming->getKey()])
        ->fillForm(['seats_offered' => 2])
        ->call('save')
        ->assertHasFormErrors(['seats_offered']);

    expect($this->upcoming->refresh()->seats_offered)->toBe(4);
});

it('refuses two stops that no corridor runs between', function () {
    $this->actingAs($this->admin);

    $lonely = Stop::factory()->create(['name' => 'Lonely']);

    Livewire::test(EditRide::class, ['record' => $this->departed->getKey()])
        ->fillForm(['destination_stop_id' => $lonely->getKey()])
        ->call('save')
        // Keyed on the field, not thrown at the page: the service refuses it
        // and the page maps the message back onto the form.
        ->assertHasFormErrors(['destination_stop_id']);

    expect($this->departed->refresh()->destination_stop_id)->toBe($this->dhaka->getKey());
});

it('lets an admin set a departure a driver could not', function () {
    $this->actingAs($this->admin);

    // Publishing insists on 15 minutes' lead and a 30 day horizon. Correcting
    // the record of a trip that has already run cannot live inside that.
    Livewire::test(EditRide::class, ['record' => $this->departed->getKey()])
        ->fillForm(['departs_at' => Carbon::now()->subDays(3)->setSeconds(0)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->departed->refresh()->departs_at->isPast())->toBeTrue();
});
