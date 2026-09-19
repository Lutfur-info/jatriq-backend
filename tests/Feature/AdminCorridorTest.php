<?php

use App\Filament\Resources\TravelRoutes\Pages\CreateTravelRoute;
use App\Filament\Resources\TravelRoutes\Pages\EditTravelRoute;
use App\Filament\Resources\TravelRoutes\Pages\ListTravelRoutes;
use App\Filament\Resources\TravelRoutes\RelationManagers\StopsRelationManager;
use App\Filament\Resources\TravelRoutes\TravelRouteResource;
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
 * Laying out a road from the corridor's end. The sequences are the corridor's
 * whole meaning - outbound from Dhaka, so a ride toward Dhaka runs back down
 * them - and an admin never types one.
 *
 *   Noakhali   Dhaka 10 - Cumilla 20 - Laksam 30
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    $this->dhaka = Stop::factory()->create(['name' => 'Dhaka']);
    $this->cumilla = Stop::factory()->create(['name' => 'Cumilla']);
    $this->laksam = Stop::factory()->create(['name' => 'Laksam']);

    $this->noakhali = TravelRoute::factory()
        ->along($this->dhaka, $this->cumilla, $this->laksam)
        ->create(['name' => 'Dhaka - Noakhali']);
});

/**
 * The road, mounted on one corridor's edit page.
 */
function roadManager(User $admin, TravelRoute $corridor): Testable
{
    test()->actingAs($admin);

    return Livewire::test(StopsRelationManager::class, [
        'ownerRecord' => $corridor,
        'pageClass' => EditTravelRoute::class,
    ]);
}

it('keeps riders away from the corridors resource', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(TravelRouteResource::getUrl('index'))
        ->assertStatus(Response::HTTP_FORBIDDEN);
})->with(['driver', 'passenger']);

it('renders every corridor page for an admin', function () {
    $this->actingAs($this->admin);

    $this->get(TravelRouteResource::getUrl('index'))->assertOk()->assertSee('Dhaka - Noakhali');
    $this->get(TravelRouteResource::getUrl('create'))->assertOk();
    $this->get(TravelRouteResource::getUrl('edit', ['record' => $this->noakhali]))->assertOk();
});

it('lists the corridors for an admin', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListTravelRoutes::class)
        ->assertCanSeeTableRecords([$this->noakhali]);
});

it('adds a corridor, taking the slug from the name', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateTravelRoute::class)
        ->fillForm(['name' => "Dhaka - Cox's Bazar"])
        ->call('create')
        ->assertHasNoFormErrors();

    $corridor = TravelRoute::query()->where('name', "Dhaka - Cox's Bazar")->sole();

    expect($corridor->slug)->toBe('dhaka-coxs-bazar')
        ->and($corridor->is_active)->toBeTrue()
        // An empty road: the towns are the next job.
        ->and($corridor->stops()->count())->toBe(0);
});

it('refuses a corridor name already in use', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateTravelRoute::class)
        ->fillForm(['name' => 'Dhaka - Noakhali'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    expect(TravelRoute::query()->where('name', 'Dhaka - Noakhali')->count())->toBe(1);
});

it('keeps the slug fixed when a corridor is renamed', function () {
    $this->actingAs($this->admin);

    $slug = $this->noakhali->slug;

    Livewire::test(EditTravelRoute::class, ['record' => $this->noakhali->getKey()])
        ->fillForm(['name' => 'Dhaka - Noakhali (N1 branch)'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->noakhali->refresh();

    // The seeder matches corridors on the slug: a slug that followed the
    // rename would make the next seed create a second corridor rather than
    // update this one.
    expect($this->noakhali->name)->toBe('Dhaka - Noakhali (N1 branch)')
        ->and($this->noakhali->slug)->toBe($slug);
});

it('closes a road without touching what is published on it', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditTravelRoute::class, ['record' => $this->noakhali->getKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->noakhali->refresh()->is_active)->toBeFalse()
        ->and($this->noakhali->stops()->count())->toBe(3);
});

it('shows the road in travel order, not alphabetically', function () {
    roadManager($this->admin, $this->noakhali)
        ->assertCanSeeTableRecords([$this->dhaka, $this->cumilla, $this->laksam], inOrder: true);
});

it('adds a town to the end of the road', function () {
    $sonaimuri = Stop::factory()->create(['name' => 'Sonaimuri']);

    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('addTown')->table(), [
            'stop_id' => $sonaimuri->getKey(),
            'placement' => 'end',
        ])
        ->assertHasNoActionErrors();

    expect($this->noakhali->stops()->pluck('name')->all())
        ->toBe(['Dhaka', 'Cumilla', 'Laksam', 'Sonaimuri'])
        ->and(sequenceOn($this->noakhali, $sonaimuri))->toBe(40);
});

it('inserts a town between two others without moving them', function () {
    $daudkandi = Stop::factory()->create(['name' => 'Daudkandi']);

    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('addTown')->table(), [
            'stop_id' => $daudkandi->getKey(),
            'placement' => "after:{$this->dhaka->getKey()}",
        ])
        ->assertHasNoActionErrors();

    expect($this->noakhali->stops()->pluck('name')->all())
        ->toBe(['Dhaka', 'Daudkandi', 'Cumilla', 'Laksam'])
        ->and(sequenceOn($this->noakhali, $daudkandi))->toBe(15)
        // The whole point of the tens: nothing else moved.
        ->and(sequenceOn($this->noakhali, $this->dhaka))->toBe(10)
        ->and(sequenceOn($this->noakhali, $this->cumilla))->toBe(20)
        ->and(sequenceOn($this->noakhali, $this->laksam))->toBe(30);
});

it('puts a brand new town on the road in one go', function () {
    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('addTown')->table(), [
            // What the select's "+" hands back: a town created inside the
            // same modal, then placed.
            'stop_id' => Stop::query()->create([
                'name' => 'Chowmuhani',
                'district_id' => District::factory()->create(['name' => 'Noakhali'])->getKey(),
            ])->getKey(),
            'placement' => 'end',
        ])
        ->assertHasNoActionErrors();

    $chowmuhani = Stop::query()->where('name', 'Chowmuhani')->sole();

    expect($chowmuhani->is_active)->toBeTrue()
        ->and(sequenceOn($this->noakhali, $chowmuhani))->toBe(40);
});

it('will not offer a town that is already on the road', function () {
    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('addTown')->table(), [
            'stop_id' => $this->cumilla->getKey(),
            'placement' => 'end',
        ])
        ->assertHasActionErrors(['stop_id']);

    expect($this->noakhali->stops()->count())->toBe(3);
});

it('refuses a gap with no whole number left in it', function () {
    $tight = Stop::factory()->create(['name' => 'Tight']);
    $this->noakhali->stops()->attach($tight, ['sequence' => 11]);

    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('addTown')->table(), [
            'stop_id' => Stop::factory()->create(['name' => 'Newcomer'])->getKey(),
            'placement' => "after:{$this->dhaka->getKey()}",
        ])
        ->assertHasActionErrors(['placement']);

    expect($this->noakhali->stops()->count())->toBe(4);
});

it('moves a town along the road', function () {
    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('move')->table($this->laksam), [
            'placement' => "after:{$this->dhaka->getKey()}",
        ])
        ->assertHasNoActionErrors();

    expect($this->noakhali->stops()->pluck('name')->all())
        ->toBe(['Dhaka', 'Laksam', 'Cumilla'])
        ->and(sequenceOn($this->noakhali, $this->laksam))->toBe(15);
});

it('takes a town off a road nobody rides from', function () {
    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('detach')->table($this->laksam))
        ->assertHasNoActionErrors();

    expect($this->noakhali->stops()->pluck('name')->all())->toBe(['Dhaka', 'Cumilla'])
        // The town survives; it is only off this road.
        ->and(Stop::query()->whereKey($this->laksam->getKey())->exists())->toBeTrue();
});

it('will not detach a town this road carries a ride from, or delete the road', function () {
    Ride::factory()
        ->between($this->noakhali, $this->laksam, $this->dhaka)
        ->departingAt(Carbon::now()->addHours(3))
        ->create();

    expect(TravelRouteResource::carriesRides($this->noakhali))->toBeTrue();

    roadManager($this->admin, $this->noakhali)
        ->assertActionHidden(TestAction::make('detach')->table($this->laksam))
        // Cumilla is on the road but no ride runs from it.
        ->assertActionVisible(TestAction::make('detach')->table($this->cumilla));

    $this->actingAs($this->admin);

    Livewire::test(EditTravelRoute::class, ['record' => $this->noakhali->getKey()])
        ->assertActionHidden('delete');
});

it('deletes a road nothing has been published on', function () {
    $this->actingAs($this->admin);

    $empty = TravelRoute::factory()->create(['name' => 'Dhaka - Nowhere']);

    Livewire::test(EditTravelRoute::class, ['record' => $empty->getKey()])
        ->assertActionVisible('delete');

    expect(TravelRouteResource::carriesRides($empty))->toBeFalse();
});

it('lets an admin type the position outright', function () {
    $sonaimuri = Stop::factory()->create(['name' => 'Sonaimuri']);

    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('addTown')->table(), [
            'stop_id' => $sonaimuri->getKey(),
            'placement' => 'exact',
            'sequence' => 25,
        ])
        ->assertHasNoActionErrors();

    expect(sequenceOn($this->noakhali, $sonaimuri))->toBe(25)
        ->and($this->noakhali->stops()->pluck('name')->all())
        ->toBe(['Dhaka', 'Cumilla', 'Sonaimuri', 'Laksam']);
});

it('refuses a typed position another town already holds', function () {
    $sonaimuri = Stop::factory()->create(['name' => 'Sonaimuri']);

    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('addTown')->table(), [
            'stop_id' => $sonaimuri->getKey(),
            'placement' => 'exact',
            // Cumilla's.
            'sequence' => 20,
        ])
        ->assertHasActionErrors(['sequence']);

    expect($sonaimuri->travelRoutes()->count())->toBe(0);
});

it('moves a town onto a typed position', function () {
    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('move')->table($this->laksam), [
            'placement' => 'exact',
            'sequence' => 5,
        ])
        ->assertHasNoActionErrors();

    expect(sequenceOn($this->noakhali, $this->laksam))->toBe(5)
        ->and($this->noakhali->stops()->pluck('name')->all())
        ->toBe(['Laksam', 'Dhaka', 'Cumilla']);
});

it('lets a town keep the number it is already on when moved', function () {
    // The move form is filled with the current position, so submitting it
    // unchanged must not read as a collision with itself.
    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('move')->table($this->laksam), [
            'placement' => 'exact',
            'sequence' => 30,
        ])
        ->assertHasNoActionErrors();

    expect(sequenceOn($this->noakhali, $this->laksam))->toBe(30);
});

it('refuses a position outside the column', function (int $sequence) {
    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('move')->table($this->laksam), [
            'placement' => 'exact',
            'sequence' => $sequence,
        ])
        ->assertHasActionErrors(['sequence']);

    expect(sequenceOn($this->noakhali, $this->laksam))->toBe(30);
})->with([0, 65536]);

it('renumbers a road in tens and moves its rides with it', function () {
    // A road whose gaps are used up: nothing fits between Dhaka and Cumilla.
    $tight = Stop::factory()->create(['name' => 'Tight']);
    $this->noakhali->stops()->attach($tight, ['sequence' => 11]);

    $ride = Ride::factory()
        ->between($this->noakhali, $this->laksam, $this->dhaka)
        ->departingAt(Carbon::now()->addHours(3))
        ->create();

    expect($ride->origin_sequence)->toBe(30)
        ->and($ride->destination_sequence)->toBe(10);

    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('renumber')->table())
        ->assertHasNoActionErrors();

    // Same order, new spacing.
    expect($this->noakhali->stops()->pluck('name')->all())
        ->toBe(['Dhaka', 'Tight', 'Cumilla', 'Laksam'])
        ->and(sequenceOn($this->noakhali, $this->dhaka))->toBe(10)
        ->and(sequenceOn($this->noakhali, $tight))->toBe(20)
        ->and(sequenceOn($this->noakhali, $this->cumilla))->toBe(30)
        ->and(sequenceOn($this->noakhali, $this->laksam))->toBe(40);

    $ride->refresh();

    // The whole point: the ride moved with the road rather than being left
    // pointing at positions it no longer has.
    expect($ride->origin_sequence)->toBe(40)
        ->and($ride->destination_sequence)->toBe(10);
});

it('keeps a renumbered corridor searchable end to end', function () {
    $tight = Stop::factory()->create(['name' => 'Tight']);
    $this->noakhali->stops()->attach($tight, ['sequence' => 11]);

    $ride = Ride::factory()
        ->between($this->noakhali, $this->laksam, $this->dhaka)
        ->departingAt(Carbon::now()->addHours(3))
        ->create();

    roadManager($this->admin, $this->noakhali)
        ->callAction(TestAction::make('renumber')->table());

    // The passenger's search, through the real endpoint: Cumilla to Dhaka is
    // a leg of the ride coming down from Laksam.
    $this->getJson(route('api.rides.index', [
        'from_stop_id' => $this->cumilla->getKey(),
        'to_stop_id' => $this->dhaka->getKey(),
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.rides')
        ->assertJsonPath('data.rides.0.id', $ride->id);
});

it('leaves a road that is already spaced in tens alone', function () {
    expect(app(TravelRouteService::class)->isEvenlySpaced($this->noakhali))->toBeTrue();

    roadManager($this->admin, $this->noakhali)
        ->assertActionDisabled(TestAction::make('renumber')->table());
});

it('offers the renumber once a gap is used up', function () {
    $this->noakhali->stops()->attach(
        Stop::factory()->create(['name' => 'Tight']),
        ['sequence' => 11],
    );

    expect(app(TravelRouteService::class)->isEvenlySpaced($this->noakhali))->toBeFalse();

    roadManager($this->admin, $this->noakhali)
        ->assertActionEnabled(TestAction::make('renumber')->table());
});

it('mounts the road relation manager on the corridor page', function () {
    expect(TravelRouteResource::getRelations())->toBe([StopsRelationManager::class])
        ->and(array_keys(TravelRouteResource::getPages()))->toBe(['index', 'create', 'edit']);
});
