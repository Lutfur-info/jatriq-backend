<?php

use App\enum\CabinClass;
use App\enum\DocumentStatus;
use App\enum\DocumentType;
use App\enum\VehicleModel;
use App\enum\VerificationStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Storage::fake(config('verification.disk'));

    $this->driver = User::factory()->driver()->create();
});

/**
 * A complete vehicle payload, overridable field by field.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function vehiclePayload(array $overrides = []): array
{
    return [
        'registration_number' => 'DHAKA METRO-GA-11-2233',
        'model' => VehicleModel::HiAce->name,
        'cabin_class' => CabinClass::Ac->name,
        'seats' => 12,
        ...$overrides,
    ];
}

it('registers the vehicle with its number, type, class and seats', function () {
    $this->actingAs($this->driver)
        ->postJson(route('api.driver.vehicle.store'), vehiclePayload())
        ->assertOk()
        ->assertJsonPath('data.vehicle.registration_number', 'DHAKA METRO-GA-11-2233')
        ->assertJsonPath('data.vehicle.model', VehicleModel::HiAce->name)
        ->assertJsonPath('data.vehicle.model_label', 'Toyota HiAce')
        ->assertJsonPath('data.vehicle.cabin_class', CabinClass::Ac->name)
        ->assertJsonPath('data.vehicle.cabin_class_label', 'AC')
        ->assertJsonPath('data.vehicle.seats', 12)
        ->assertJsonPath('data.driving_licence', null);

    $vehicle = $this->driver->vehicle;

    expect($vehicle)->not->toBeNull()
        ->and($vehicle->model)->toBe(VehicleModel::HiAce)
        ->and($vehicle->cabin_class)->toBe(CabinClass::Ac)
        ->and($vehicle->seats)->toBe(12);
});

it('takes the driving licence alongside the details and puts it into review', function () {
    $this->actingAs($this->driver)
        ->post(route('api.driver.vehicle.store'), vehiclePayload([
            'driving_licence' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
        ]), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.driving_licence.type', DocumentType::DrivingLicence->name)
        ->assertJsonPath('data.driving_licence.status', DocumentStatus::Pending->name)
        ->assertJsonPath('data.driving_licence.original_name', 'licence.pdf');

    $licence = $this->driver->documents()->sole();

    expect($licence->type)->toBe(DocumentType::DrivingLicence);

    Storage::disk(config('verification.disk'))->assertExists($licence->path);
});

it('stores the licence exactly as the verification endpoint would', function () {
    $this->actingAs($this->driver)
        ->post(route('api.driver.vehicle.store'), vehiclePayload([
            'driving_licence' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertOk();

    $original = $this->driver->documents()->sole();

    $this->actingAs($this->driver)
        ->post(route('api.driver.vehicle.store'), vehiclePayload([
            'driving_licence' => UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertOk();

    $replacement = $this->driver->documents()->sole();
    $disk = Storage::disk(config('verification.disk'));

    expect($this->driver->documents()->count())->toBe(1)
        ->and($replacement->id)->toBe($original->id)
        ->and($replacement->original_name)->toBe('second.pdf');

    $disk->assertMissing($original->path);
    $disk->assertExists($replacement->path);
});

it('leaves the badge alone, because a licence is not part of the required set', function () {
    $this->actingAs($this->driver)
        ->post(route('api.driver.vehicle.store'), vehiclePayload([
            'driving_licence' => UploadedFile::fake()->create('licence.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertOk();

    expect($this->driver->refresh()->verification_status)->toBe(VerificationStatus::Unverified);
});

it('replaces the details rather than registering a second vehicle', function () {
    $this->actingAs($this->driver);

    $this->postJson(route('api.driver.vehicle.store'), vehiclePayload())->assertOk();

    $first = $this->driver->vehicle;

    $this->postJson(route('api.driver.vehicle.store'), vehiclePayload([
        'registration_number' => 'DHAKA METRO-KHA-14-5566',
        'model' => VehicleModel::Corolla->name,
        'cabin_class' => CabinClass::NonAc->name,
        'seats' => 4,
    ]))
        ->assertOk()
        ->assertJsonPath('data.vehicle.id', $first->id)
        ->assertJsonPath('data.vehicle.registration_number', 'DHAKA METRO-KHA-14-5566')
        ->assertJsonPath('data.vehicle.seats', 4);

    expect(Vehicle::query()->where('user_id', $this->driver->id)->count())->toBe(1);
});

it('accepts the driver resubmitting their own vehicle number', function () {
    $this->actingAs($this->driver);

    $this->postJson(route('api.driver.vehicle.store'), vehiclePayload())->assertOk();

    $this->postJson(route('api.driver.vehicle.store'), vehiclePayload(['seats' => 10]))
        ->assertOk()
        ->assertJsonPath('data.vehicle.seats', 10);
});

it('refuses a vehicle number another driver has registered', function () {
    Vehicle::factory()->create(['registration_number' => 'DHAKA METRO-GA-11-2233']);

    $this->actingAs($this->driver)
        ->postJson(route('api.driver.vehicle.store'), vehiclePayload())
        ->assertJsonValidationErrorFor('registration_number');
});

it('folds a plate down to one spelling so the same vehicle collides', function () {
    Vehicle::factory()->create(['registration_number' => 'DHAKA METRO-GA-11-2233']);

    $this->actingAs($this->driver)
        ->postJson(route('api.driver.vehicle.store'), vehiclePayload([
            'registration_number' => '  dhaka   metro-ga-11-2233 ',
        ]))
        ->assertJsonValidationErrorFor('registration_number');
});

it('stores the plate uppercased and single spaced', function () {
    $this->actingAs($this->driver)
        ->postJson(route('api.driver.vehicle.store'), vehiclePayload([
            'registration_number' => ' dhaka   metro-ga-11-2233',
        ]))
        ->assertOk()
        ->assertJsonPath('data.vehicle.registration_number', 'DHAKA METRO-GA-11-2233');
});

it('requires every detail', function () {
    $this->actingAs($this->driver)
        ->postJson(route('api.driver.vehicle.store'), [])
        ->assertJsonValidationErrors(['registration_number', 'model', 'cabin_class', 'seats']);
});

it('refuses a vehicle type or class it does not know', function () {
    $this->actingAs($this->driver)
        ->postJson(route('api.driver.vehicle.store'), vehiclePayload([
            'model' => 'Pajero',
            'cabin_class' => 'Chilled',
        ]))
        ->assertJsonValidationErrors(['model', 'cabin_class']);
});

it('holds the seat count inside the configured range', function () {
    $this->actingAs($this->driver);

    $this->postJson(route('api.driver.vehicle.store'), vehiclePayload([
        'seats' => ((int) config('vehicles.seats.min')) - 1,
    ]))->assertJsonValidationErrorFor('seats');

    $this->postJson(route('api.driver.vehicle.store'), vehiclePayload([
        'seats' => ((int) config('vehicles.seats.max')) + 1,
    ]))->assertJsonValidationErrorFor('seats');

    expect($this->driver->vehicle)->toBeNull();
});

it('accepts a vehicle that carries a single passenger', function () {
    // `seats` counts passenger seats, so one is a whole vehicle - a rickshaw
    // style two-seater with the driver in the other seat. The old meaning
    // counted the driver too and refused anything under two.
    $this->actingAs($this->driver)
        ->postJson(route('api.driver.vehicle.store'), vehiclePayload(['seats' => 1]))
        ->assertOk()
        ->assertJsonPath('data.vehicle.seats', 1);

    expect($this->driver->vehicle->seats)->toBe(1);
});

it('offers passenger seats, not seat totals, as the starting point', function () {
    // One below the totals the models are sold with: a 12-seat HiAce carries
    // 11 passengers. Pinned by value because the client prefills the field
    // from it, so an off-by-one here is an off-by-one in every registration.
    expect(VehicleModel::HiAce->typicalSeats())->toBe(11)
        ->and(VehicleModel::Noah->typicalSeats())->toBe(6)
        ->and(VehicleModel::Corolla->typicalSeats())->toBe(3);
});

it('refuses a licence that is not a scan or a photograph', function () {
    $this->actingAs($this->driver)
        ->post(route('api.driver.vehicle.store'), vehiclePayload([
            'driving_licence' => UploadedFile::fake()->create('licence.txt', 10, 'text/plain'),
        ]), ['Accept' => 'application/json'])
        ->assertJsonValidationErrorFor('driving_licence');
});

it('refuses a licence larger than the configured ceiling', function () {
    $oversized = ((int) config('verification.max_size')) + 1;

    $this->actingAs($this->driver)
        ->post(route('api.driver.vehicle.store'), vehiclePayload([
            'driving_licence' => UploadedFile::fake()->create('huge.pdf', $oversized, 'application/pdf'),
        ]), ['Accept' => 'application/json'])
        ->assertJsonValidationErrorFor('driving_licence');
});

it('reports no vehicle and offers the choices before anything is registered', function () {
    $this->actingAs($this->driver)
        ->getJson(route('api.driver.vehicle.show'))
        ->assertOk()
        ->assertJsonPath('data.vehicle', null)
        ->assertJsonPath('data.driving_licence', null)
        ->assertJsonPath('data.options.models.0.value', VehicleModel::HiAce->name)
        ->assertJsonPath('data.options.models.0.typical_seats', VehicleModel::HiAce->typicalSeats())
        ->assertJsonCount(count(VehicleModel::cases()), 'data.options.models')
        ->assertJsonCount(count(CabinClass::cases()), 'data.options.cabin_classes')
        ->assertJsonPath('data.options.seats.min', (int) config('vehicles.seats.min'))
        ->assertJsonPath('data.options.seats.max', (int) config('vehicles.seats.max'));
});

it('reads back the registered vehicle', function () {
    $vehicle = Vehicle::factory()
        ->for($this->driver)
        ->ofModel(VehicleModel::Noah)
        ->airConditioned()
        ->create();

    $this->actingAs($this->driver)
        ->getJson(route('api.driver.vehicle.show'))
        ->assertOk()
        ->assertJsonPath('data.vehicle.id', $vehicle->id)
        ->assertJsonPath('data.vehicle.model', VehicleModel::Noah->name)
        ->assertJsonPath('data.vehicle.cabin_class', CabinClass::Ac->name)
        ->assertJsonPath('data.vehicle.seats', VehicleModel::Noah->typicalSeats());
});

it('keeps one drivers vehicle out of anothers response', function () {
    Vehicle::factory()->create();

    $this->actingAs($this->driver)
        ->getJson(route('api.driver.vehicle.show'))
        ->assertOk()
        ->assertJsonPath('data.vehicle', null);
});

it('is closed to a passenger, who has no vehicle', function () {
    $passenger = User::factory()->passenger()->create();

    $this->actingAs($passenger)
        ->getJson(route('api.driver.vehicle.show'))
        ->assertStatus(Response::HTTP_FORBIDDEN);

    $this->actingAs($passenger)
        ->postJson(route('api.driver.vehicle.store'), vehiclePayload())
        ->assertStatus(Response::HTTP_FORBIDDEN);

    expect(Vehicle::query()->count())->toBe(0);
});

it('is closed to an admin', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('api.driver.vehicle.show'))
        ->assertStatus(Response::HTTP_FORBIDDEN);
});

it('requires authentication', function () {
    $this->getJson(route('api.driver.vehicle.show'))->assertUnauthorized();
});

it('deletes the vehicle with the driver', function () {
    Vehicle::factory()->for($this->driver)->create();

    $this->driver->delete();

    expect(Vehicle::query()->count())->toBe(0);
});
