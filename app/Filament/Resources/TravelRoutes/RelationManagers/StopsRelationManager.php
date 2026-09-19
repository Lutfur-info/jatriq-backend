<?php

namespace App\Filament\Resources\TravelRoutes\RelationManagers;

use App\Filament\Resources\Stops\StopResource;
use App\Filament\Resources\TravelRoutes\Schemas\PlacementSelect;
use App\Models\District;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Services\TravelRouteService;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The road: every town on this corridor, in the order a vehicle meets them.
 *
 * Read top to bottom it is the drive out of Dhaka. That ordering is not
 * decoration - it is how direction is represented in the whole system, so the
 * table is never sorted by anything else and the actions place one town at a
 * time rather than offering a reorder.
 *
 * Adding a town is one modal: pick it from the catalogue (or create it right
 * there), then say which two towns it sits between. Nobody types a sequence
 * number - `TravelRouteService` works it out of the gap, which is what keeps
 * the tens intact and published rides where they are.
 */
class StopsRelationManager extends RelationManager
{
    protected static string $relationship = 'stops';

    protected static ?string $title = 'The road';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description('Outbound from Dhaka, top to bottom. A ride toward Dhaka is one running back up this list.')
            ->columns([
                TextColumn::make('sequence')
                    ->label('#')
                    ->state(fn (Stop $record): int => $this->sequenceOf($record))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label('Town')
                    ->searchable()
                    ->description(fn (Stop $record): ?string => $record->district?->name),

                IconColumn::make('is_active')
                    ->label('Offered')
                    ->boolean()
                    ->tooltip(fn (Stop $record): ?string => $record->is_active
                        ? null
                        : 'Retired: it keeps its place on the road but no longer appears in a picker.'),
            ])
            // No ->defaultSort(): the relation is already ordered by the
            // pivot's sequence, and any other order would misread the road.
            ->headerActions([
                Action::make('addTown')
                    ->label('Add a town')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading('Put a town on this road')
                    ->modalSubmitActionLabel('Add it')
                    ->schema([
                        Select::make('stop_id')
                            ->label('Town')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => $this->townsNotOnTheRoad())
                            ->helperText('Not in the list? It is already on this road. Use + for a town the country does not have yet.')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Town')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('stops', 'name'),
                                Select::make('district_id')
                                    ->label('District')
                                    // Picked, never typed - the same reason
                                    // the full stop form picks it. Options
                                    // rather than `relationship()`, because
                                    // there is no stop to relate to yet.
                                    ->options(fn (): array => District::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->helperText('So two same-named towns read apart in a picker.'),
                            ])
                            ->createOptionModalHeading('Add a town to the catalogue')
                            // The short form: a town created here is on this
                            // road immediately, and the rest of its details
                            // are edited under Stops.
                            ->createOptionUsing(fn (array $data): int => Stop::query()->create($data)->getKey()),

                        ...PlacementSelect::fields(fn (): TravelRoute => $this->corridor()),
                    ])
                    ->action(function (array $data): void {
                        $stop = Stop::query()->whereKey($data['stop_id'])->firstOrFail();

                        $sequence = app(TravelRouteService::class)
                            ->putStopOn($this->corridor(), $stop, PlacementSelect::resolve($data));

                        Notification::make()
                            ->success()
                            ->title("{$stop->name} is on the road")
                            ->body("Position {$sequence}, outbound from Dhaka.")
                            ->send();
                    }),

                Action::make('renumber')
                    ->label('Renumber the road')
                    ->icon(Heroicon::OutlinedCalculator)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Re-space this road in tens?')
                    ->modalDescription('The towns keep their order; only the numbers between them change, back to 10, 20, 30 and so on. Every ride published on this corridor is rewritten onto the new positions in the same transaction, so nothing is left pointing at a position the road no longer has. Do this when a gap between two towns has no room left in it.')
                    ->modalSubmitActionLabel('Renumber')
                    // Nothing to gain, and the rides on it nothing to risk.
                    ->disabled(fn (): bool => app(TravelRouteService::class)->isEvenlySpaced($this->corridor()))
                    ->tooltip(fn (): ?string => app(TravelRouteService::class)->isEvenlySpaced($this->corridor())
                        ? 'Already spaced in tens.'
                        : null)
                    ->action(function (): void {
                        $counts = app(TravelRouteService::class)->renumber($this->corridor());

                        Notification::make()
                            ->success()
                            ->title('Road renumbered')
                            ->body("{$counts['towns']} towns re-spaced in tens, and {$counts['rides']} rides moved with them.")
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('move')
                    ->label('Move')
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->color('gray')
                    ->modalHeading(fn (Stop $record): string => "Move {$record->name} along this road")
                    ->modalDescription('Rides already published carry a copy of their positions, so moving a town does not move them.')
                    ->fillForm(fn (Stop $record): array => [
                        // The number it is on now, so "set the number myself"
                        // starts from where the town actually is.
                        'sequence' => $this->sequenceOf($record),
                    ])
                    ->schema(fn (Stop $record): array => PlacementSelect::fields(
                        fn (): TravelRoute => $this->corridor(),
                        $record,
                    ))
                    ->action(function (Stop $record, array $data): void {
                        $sequence = app(TravelRouteService::class)
                            ->moveStopOn($this->corridor(), $record, PlacementSelect::resolve($data));

                        Notification::make()
                            ->success()
                            ->title("{$record->name} moved")
                            ->body("Now at position {$sequence}.")
                            ->send();
                    }),

                Action::make('openTown')
                    ->label('Open town')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Stop $record): string => StopResource::getUrl('edit', ['record' => $record])),

                DetachAction::make()
                    ->label('Take off the road')
                    ->modalHeading(fn (Stop $record): string => "Take {$record->name} off this road?")
                    ->modalDescription('The town stays in the catalogue and on every other corridor it sits on.')
                    // A ride published along this corridor from this town is
                    // matched through the pivot: taking it off would leave
                    // the ride on the board but out of every search.
                    ->hidden(fn (Stop $record): bool => $this->carriesARideFrom($record)),
            ]);
    }

    /**
     * The catalogue minus the towns already on this road.
     *
     * @return array<int, string>
     */
    private function townsNotOnTheRoad(): array
    {
        return Stop::query()
            ->whereDoesntHave('travelRoutes', fn ($corridors) => $corridors->whereKey($this->corridor()->getKey()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Whether a ride on this corridor runs from or to the given town.
     */
    private function carriesARideFrom(Stop $stop): bool
    {
        return Ride::query()
            ->where('travel_route_id', $this->corridor()->getKey())
            ->where(fn ($query) => $query
                ->where('origin_stop_id', $stop->getKey())
                ->orWhere('destination_stop_id', $stop->getKey()))
            ->exists();
    }

    /**
     * A town's position on the road it was read through.
     */
    private function sequenceOf(Stop $stop): int
    {
        $sequence = $stop->getAttribute('pivot')?->getAttribute('sequence');

        return is_numeric($sequence) ? (int) $sequence : 0;
    }

    /**
     * The road this manager hangs off.
     */
    private function corridor(): TravelRoute
    {
        /** @var TravelRoute $corridor */
        $corridor = $this->getOwnerRecord();

        return $corridor;
    }
}
