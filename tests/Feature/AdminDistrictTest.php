<?php

use App\Filament\Resources\Districts\DistrictResource;
use App\Filament\Resources\Districts\Pages\ListDistricts;
use App\Filament\Resources\Stops\Pages\CreateStop;
use App\Models\District;
use App\Models\Stop;
use App\Models\User;
use Database\Seeders\DistrictSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/*
 * The district, taken out of the stop and made a row of its own.
 *
 * As free text nothing stopped "Cumilla", "cumilla" and "Comilla" being three
 * districts, and an admin had to remember how the last town was filed. A stop
 * points at one now, and the picker is the whole of the change.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    $this->noakhali = District::factory()->create(['name' => 'Noakhali']);
    $this->cumilla = District::factory()->create(['name' => 'Cumilla']);
});

it('keeps riders away from the districts resource', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(DistrictResource::getUrl('index'))
        ->assertStatus(Response::HTTP_FORBIDDEN);
})->with(['driver', 'passenger']);

it('lists the districts for an admin', function () {
    $this->actingAs($this->admin);

    $this->get(DistrictResource::getUrl('index'))->assertOk();

    Livewire::test(ListDistricts::class)
        ->assertCanSeeTableRecords([$this->noakhali, $this->cumilla]);
});

it('adds a district from the panel', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListDistricts::class)
        ->callAction(TestAction::make('create'), ['name' => 'Feni'])
        ->assertHasNoActionErrors();

    expect(District::query()->where('name', 'Feni')->exists())->toBeTrue();
});

it('refuses a district that is already in the list', function () {
    $this->actingAs($this->admin);

    // The uniqueness is the whole reason the table exists: a second
    // "Cumilla" would put the same district in the picker twice.
    Livewire::test(ListDistricts::class)
        ->callAction(TestAction::make('create'), ['name' => 'Cumilla'])
        ->assertHasActionErrors(['name' => 'unique']);

    expect(District::query()->where('name', 'Cumilla')->count())->toBe(1);
});

it('picks a stop\'s district instead of typing it', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateStop::class)
        ->assertFormFieldExists('district_id')
        // The old free-text field is gone, and so is the place id.
        ->assertFormFieldDoesNotExist('district')
        ->assertFormFieldDoesNotExist('place_id')
        ->fillForm(['name' => 'Sonaimuri', 'district_id' => $this->noakhali->getKey()])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Stop::query()->where('name', 'Sonaimuri')->sole()->district->name)->toBe('Noakhali');
});

it('leaves a town usable with no district at all', function () {
    $this->actingAs($this->admin);

    // A town is usable the moment it has a name; the district is a label.
    Livewire::test(CreateStop::class)
        ->fillForm(['name' => 'Bare'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Stop::query()->where('name', 'Bare')->sole()->district)->toBeNull();
});

it('keeps the towns when a district is deleted', function () {
    $stop = Stop::factory()->create(['district_id' => $this->noakhali->getKey()]);

    // `nullOnDelete`, not `restrictOnDelete`: a district is decoration on a
    // picker label, so losing one must never take a town off the network.
    $this->noakhali->delete();

    expect($stop->refresh()->exists)->toBeTrue()
        ->and($stop->district_id)->toBeNull();
});

it('hides delete on a district that has towns filed under it', function () {
    Stop::factory()->create(['district_id' => $this->noakhali->getKey()]);

    expect(DistrictResource::holdsStops($this->noakhali))->toBeTrue()
        ->and(DistrictResource::holdsStops($this->cumilla))->toBeFalse();
});

it('seeds all 64 districts, and re-seeds without doubling them', function () {
    $this->seed(DistrictSeeder::class);

    expect(District::query()->count())->toBe(64);

    // Reference data, like the corridors: safe to re-run on a live database.
    $this->seed(DistrictSeeder::class);

    expect(District::query()->count())->toBe(64)
        // The two the beforeEach made are part of the 64, matched by name
        // rather than created a second time.
        ->and(District::query()->where('name', 'Noakhali')->count())->toBe(1);
});
