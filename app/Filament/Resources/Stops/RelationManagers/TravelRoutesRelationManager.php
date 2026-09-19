<?php

namespace App\Filament\Resources\Stops\RelationManagers;

use App\Filament\Resources\TravelRoutes\Schemas\PlacementSelect;
use App\Filament\Resources\TravelRoutes\TravelRouteResource;
use App\Models\Ride;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Services\TravelRouteService;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The same pivot as `StopsRelationManager`, read from the other end: which
 * roads this one town sits on, and where along each.
 *
 * Both ends exist because both questions get asked. Laying out a road is a
 * corridor's job; putting Cumilla on the three corridors that pass through it
 * is the town's, and doing that from three separate corridor pages would lose
 * the thing worth seeing - that it is one town shared, not three copies.
 *
 * Placement is by neighbours, never by number, for the reason given on
 * `PlacementSelect`.
 */
class TravelRoutesRelationManager extends RelationManager
{
    protected static string $relationship = 'travelRoutes';

    protected static ?string $title = 'Corridors';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description('Every road this town sits on. It is one town shared by all of them, which is what lets a search from here find the vehicles coming down each.')
            ->columns([
                TextColumn::make('name')
                    ->label('Corridor')
                    ->searchable()
                    ->url(fn (TravelRoute $record): string => TravelRouteResource::getUrl('edit', ['record' => $record])),

                TextColumn::make('sequence')
                    ->label('#')
                    ->state(fn (TravelRoute $record): int => $this->sequenceOf($record))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('neighbours')
                    ->label('Between')
                    ->state(fn (TravelRoute $record): string => $this->neighbours($record))
                    ->wrap(),

                IconColumn::make('is_active')
                    ->label('Road open')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->headerActions([
                Action::make('addToCorridor')
                    ->label('Put on a corridor')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading(fn (): string => "Put {$this->stop()->name} on a corridor")
                    ->modalSubmitActionLabel('Add it')
                    ->schema([
                        Select::make('travel_route_id')
                            ->label('Corridor')
                            ->required()
                            ->searchable()
                            ->preload()
                            // Live so the placements below can be built from
                            // the road actually chosen.
                            ->live()
                            ->options(fn (): array => $this->corridorsItIsNotOn()),

                        ...PlacementSelect::fields(fn (Get $get): ?TravelRoute => $this->corridorFrom($get('travel_route_id'))),
                    ])
                    ->action(function (array $data): void {
                        $corridor = TravelRoute::query()->whereKey($data['travel_route_id'])->firstOrFail();

                        $sequence = app(TravelRouteService::class)
                            ->putStopOn($corridor, $this->stop(), PlacementSelect::resolve($data));

                        Notification::make()
                            ->success()
                            ->title("On {$corridor->name}")
                            ->body("Position {$sequence}, outbound from Dhaka.")
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('move')
                    ->label('Move')
                    ->icon(Heroicon::OutlinedArrowsUpDown)
                    ->color('gray')
                    ->modalHeading(fn (TravelRoute $record): string => "Move {$this->stop()->name} along {$record->name}")
                    ->modalDescription('Rides already published carry a copy of their positions, so moving a town does not move them.')
                    ->fillForm(fn (TravelRoute $record): array => [
                        'sequence' => $this->sequenceOf($record),
                    ])
                    ->schema(fn (TravelRoute $record): array => PlacementSelect::fields(
                        fn (): TravelRoute => $record,
                        $this->stop(),
                    ))
                    ->action(function (TravelRoute $record, array $data): void {
                        $sequence = app(TravelRouteService::class)
                            ->moveStopOn($record, $this->stop(), PlacementSelect::resolve($data));

                        Notification::make()
                            ->success()
                            ->title("Moved along {$record->name}")
                            ->body("Now at position {$sequence}.")
                            ->send();
                    }),

                DetachAction::make()
                    ->label('Take off this road')
                    ->modalDescription('The town stays in the catalogue; it just stops being on this corridor.')
                    ->hidden(fn (TravelRoute $record): bool => $this->carriesARide($record)),
            ]);
    }

    /**
     * The roads this town is not on yet.
     *
     * @return array<int, string>
     */
    private function corridorsItIsNotOn(): array
    {
        return TravelRoute::query()
            ->whereDoesntHave('stops', fn ($stops) => $stops->whereKey($this->stop()->getKey()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * One corridor by the id the select holds, or null before one is chosen.
     */
    private function corridorFrom(mixed $id): ?TravelRoute
    {
        if (! is_numeric($id)) {
            return null;
        }

        return TravelRoute::query()->whereKey((int) $id)->first();
    }

    /**
     * The two towns this one sits between on a corridor.
     */
    private function neighbours(TravelRoute $route): string
    {
        $sequence = $this->sequenceOf($route);

        $before = $route->stops()->wherePivot('sequence', '<', $sequence)
            ->orderByDesc('travel_route_stop.sequence')->first();

        $after = $route->stops()->wherePivot('sequence', '>', $sequence)
            ->orderBy('travel_route_stop.sequence')->first();

        return ($before instanceof Stop ? $before->name : 'start of the road')
            .' → '
            .($after instanceof Stop ? $after->name : 'end of the road');
    }

    /**
     * Whether a ride on this corridor runs from or to this town.
     */
    private function carriesARide(TravelRoute $route): bool
    {
        $stopId = $this->stop()->getKey();

        return Ride::query()
            ->where('travel_route_id', $route->getKey())
            ->where(fn ($query) => $query
                ->where('origin_stop_id', $stopId)
                ->orWhere('destination_stop_id', $stopId))
            ->exists();
    }

    /**
     * This town's position on the corridor the row came through.
     */
    private function sequenceOf(TravelRoute $route): int
    {
        $sequence = $route->getAttribute('pivot')?->getAttribute('sequence');

        return is_numeric($sequence) ? (int) $sequence : 0;
    }

    /**
     * The town this manager hangs off.
     */
    private function stop(): Stop
    {
        /** @var Stop $stop */
        $stop = $this->getOwnerRecord();

        return $stop;
    }
}
