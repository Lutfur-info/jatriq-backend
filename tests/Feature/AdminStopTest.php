<?php

use App\Filament\Resources\Stops\Pages\CreateStop;
use App\Filament\Resources\Stops\Pages\EditStop;
use App\Filament\Resources\Stops\Pages\ListStops;
use App\Filament\Resources\Stops\RelationManagers\TravelRoutesRelationManager;
use App\Filament\Resources\Stops\StopResource;
use App\Models\District;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Models\User;
use App\Services\TravelRouteService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/*
 * Adding a town to the network by hand, which until now only the seeder could
 * do. Two halves: the place itself, and where it sits on each corridor - a
 * stop on no corridor reaches no picker and no search.
 *
 *   Noakhali   Dhaka 10 - Cumilla 20 - Laksam 30
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    $this->noakhaliDistrict = District::factory()->create(['name' => 'Noakhali']);
    $this->cumillaDistrict = District::factory()->create(['name' => 'Cumilla']);

    $this->dhaka = Stop::factory()->create(['name' => 'Dhaka']);
    $this->cumilla = Stop::factory()->create(['name' => 'Cumilla']);
    $this->laksam = Stop::factory()->create(['name' => 'Laksam']);

    $this->noakhali = TravelRoute::factory()
        ->along($this->dhaka, $this->cumilla, $this->laksam)
        ->create(['name' => 'Dhaka - Noakhali']);
});

/**
 * The corridors table, mounted on one stop's edit page.
 */
function corridorsManager(User $admin, Stop $stop): Testable
{
    test()->actingAs($admin);

    return Livewire::test(TravelRoutesRelationManager::class, [
        'ownerRecord' => $stop,
        'pageClass' => EditStop::class,
    ]);
}

it('keeps riders away from the stops resource', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(StopResource::getUrl('index'))
        ->assertStatus(Response::HTTP_FORBIDDEN);
})->with(['driver', 'passenger']);

it('renders every stops page for an admin', function () {
    $this->actingAs($this->admin);

    $this->get(StopResource::getUrl('index'))->assertOk()->assertSee('Cumilla');
    $this->get(StopResource::getUrl('create'))->assertOk();
    $this->get(StopResource::getUrl('edit', ['record' => $this->laksam]))
        ->assertOk()
        ->assertSee('Laksam');
});

it('lists the towns for an admin', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListStops::class)
        ->assertCanSeeTableRecords([$this->dhaka, $this->cumilla, $this->laksam]);
});

it('adds a stop from the panel', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateStop::class)
        ->fillForm([
            'name' => 'Sonaimuri',
            // Picked from the districts table, never typed - as free text
            // the same district could be spelled three ways.
            'district_id' => $this->noakhaliDistrict->getKey(),
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $stop = Stop::query()->where('name', 'Sonaimuri')->sole();

    expect($stop->district->name)->toBe('Noakhali')
        ->and($stop->is_active)->toBeTrue()
        // Nothing yet: putting it on a corridor is the other half.
        ->and($stop->travelRoutes()->count())->toBe(0);
});

it('refuses a town that is already in the catalogue', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateStop::class)
        ->fillForm(['name' => 'Cumilla'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    expect(Stop::query()->where('name', 'Cumilla')->count())->toBe(1);
});

it('demands a name, which is all a town needs', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateStop::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);

    // Everything else about a town is optional: coordinates were dropped on
    // 2026-09-18, the Google place id on 2026-09-19, and the district is only
    // there to tell two same-named towns apart.
    Livewire::test(CreateStop::class)
        ->fillForm(['name' => 'Bare'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Stop::query()->where('name', 'Bare')->exists())->toBeTrue();
});

it('edits and retires a town without touching the rides published from it', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditStop::class, ['record' => $this->laksam->getKey()])
        ->fillForm(['district_id' => $this->cumillaDistrict->getKey(), 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->laksam->refresh();

    expect($this->laksam->district->name)->toBe('Cumilla')
        ->and($this->laksam->is_active)->toBeFalse()
        // Retiring hides it from the pickers; it stays on the corridor.
        ->and($this->laksam->travelRoutes()->count())->toBe(1);
});

it('puts a new town on a corridor by naming its neighbours', function () {
    $sonaimuri = Stop::factory()->create(['name' => 'Sonaimuri']);

    corridorsManager($this->admin, $sonaimuri)
        ->callAction(TestAction::make('addToCorridor')->table(), [
            'travel_route_id' => $this->noakhali->getKey(),
            // Nobody types a number: this is "at the end, after Laksam".
            'placement' => 'end',
        ])
        ->assertHasNoActionErrors();

    expect($this->noakhali->stops()->pluck('name')->all())
        ->toBe(['Dhaka', 'Cumilla', 'Laksam', 'Sonaimuri'])
        // Laksam sits at 30, so the road carries on in tens.
        ->and(sequenceOn($this->noakhali, $sonaimuri))->toBe(40);
});

it('drops a town into the gap between two others', function () {
    $daudkandi = Stop::factory()->create(['name' => 'Daudkandi']);

    corridorsManager($this->admin, $daudkandi)
        ->callAction(TestAction::make('addToCorridor')->table(), [
            'travel_route_id' => $this->noakhali->getKey(),
            'placement' => "after:{$this->dhaka->getKey()}",
        ])
        ->assertHasNoActionErrors();

    expect($this->noakhali->stops()->pluck('name')->all())
        ->toBe(['Dhaka', 'Daudkandi', 'Cumilla', 'Laksam'])
        // Halfway between Dhaka at 10 and Cumilla at 20.
        ->and(sequenceOn($this->noakhali, $daudkandi))->toBe(15);
});

it('refuses a gap with no whole number left in it', function () {
    // Two towns one apart: nothing fits between them without renumbering the
    // corridor, which would mis-position every ride published on it.
    $tight = Stop::factory()->create(['name' => 'Tight']);
    $this->noakhali->stops()->attach($tight, ['sequence' => 11]);

    $newcomer = Stop::factory()->create(['name' => 'Newcomer']);

    corridorsManager($this->admin, $newcomer)
        ->callAction(TestAction::make('addToCorridor')->table(), [
            'travel_route_id' => $this->noakhali->getKey(),
            'placement' => "after:{$this->dhaka->getKey()}",
        ])
        ->assertHasActionErrors(['placement']);

    expect($newcomer->travelRoutes()->count())->toBe(0);
});

it('takes a typed position from the town\'s side too', function () {
    $sonaimuri = Stop::factory()->create(['name' => 'Sonaimuri']);

    corridorsManager($this->admin, $sonaimuri)
        ->callAction(TestAction::make('addToCorridor')->table(), [
            'travel_route_id' => $this->noakhali->getKey(),
            'placement' => 'exact',
            'sequence' => 35,
        ])
        ->assertHasNoActionErrors();

    expect(sequenceOn($this->noakhali, $sonaimuri))->toBe(35);
});

it('refuses a typed position that is taken, from the town\'s side', function () {
    $sonaimuri = Stop::factory()->create(['name' => 'Sonaimuri']);

    corridorsManager($this->admin, $sonaimuri)
        ->callAction(TestAction::make('addToCorridor')->table(), [
            'travel_route_id' => $this->noakhali->getKey(),
            'placement' => 'exact',
            // Laksam's.
            'sequence' => 30,
        ])
        ->assertHasActionErrors(['sequence']);

    expect($sonaimuri->travelRoutes()->count())->toBe(0);
});

it('moves a town along the corridor', function () {
    corridorsManager($this->admin, $this->laksam)
        ->callAction(TestAction::make('move')->table($this->noakhali), [
            'placement' => 'start',
        ])
        ->assertHasNoActionErrors();

    // Half of Dhaka's 10, so it now leads the road.
    expect(sequenceOn($this->noakhali, $this->laksam))->toBe(5)
        ->and($this->noakhali->stops()->pluck('name')->all())
        ->toBe(['Laksam', 'Dhaka', 'Cumilla']);
});

it('offers a moving town every gap except the one it is in', function () {
    $placements = app(TravelRouteService::class)->placementsOn($this->noakhali, $this->laksam);

    // Laksam is off the list while it is the one being moved, so the gaps are
    // read from Dhaka and Cumilla alone.
    expect(array_keys($placements))->toBe([
        'start',
        "after:{$this->dhaka->getKey()}",
        'end',
    ])
        ->and($placements['end']['label'])->toBe('At the end, after Cumilla')
        ->and($placements["after:{$this->dhaka->getKey()}"]['sequence'])->toBe(15);
});

it('takes a town off a corridor nobody rides from', function () {
    corridorsManager($this->admin, $this->laksam)
        ->callAction(TestAction::make('detach')->table($this->noakhali))
        ->assertHasNoActionErrors();

    expect($this->noakhali->stops()->pluck('name')->all())->toBe(['Dhaka', 'Cumilla'])
        // The town itself survives - it is only off this road.
        ->and($this->laksam->exists())->toBeTrue()
        ->and(Stop::query()->whereKey($this->laksam->getKey())->exists())->toBeTrue();
});

it('will not detach or delete a town a ride is published from', function () {
    Ride::factory()
        ->between($this->noakhali, $this->laksam, $this->dhaka)
        ->departingAt(Carbon::now()->addHours(3))
        ->create();

    expect(StopResource::isReferencedByRide($this->laksam))->toBeTrue()
        ->and(StopResource::isReferencedByRide($this->cumilla))->toBeFalse();

    corridorsManager($this->admin, $this->laksam)
        ->assertActionHidden(TestAction::make('detach')->table($this->noakhali));

    $this->actingAs($this->admin);

    Livewire::test(EditStop::class, ['record' => $this->laksam->getKey()])
        ->assertActionHidden('delete');

    Livewire::test(EditStop::class, ['record' => $this->cumilla->getKey()])
        ->assertActionVisible('delete');
});

it('finds the towns that are on no corridor at all', function () {
    $orphan = Stop::factory()->create(['name' => 'Nowhere']);

    $this->actingAs($this->admin);

    Livewire::test(ListStops::class)
        ->filterTable('unplaced')
        ->assertCanSeeTableRecords([$orphan])
        ->assertCanNotSeeTableRecords([$this->dhaka, $this->cumilla, $this->laksam]);
});

it('mounts the corridors relation manager on the edit page', function () {
    expect(StopResource::getRelations())->toBe([TravelRoutesRelationManager::class])
        ->and(array_keys(StopResource::getPages()))->toBe(['index', 'create', 'edit']);
});
