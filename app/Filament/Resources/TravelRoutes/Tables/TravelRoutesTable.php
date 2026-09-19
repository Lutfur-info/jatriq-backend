<?php

namespace App\Filament\Resources\TravelRoutes\Tables;

use App\Filament\Resources\TravelRoutes\TravelRouteResource;
use App\Models\TravelRoute;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * The nine roads, alphabetical.
 *
 * "Towns" is the column that says whether a corridor works: a road with fewer
 * than two of them carries no journey at all, because a leg needs an origin
 * and a destination on the same corridor.
 */
class TravelRoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Corridor')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stops_count')
                    ->label('Towns')
                    ->counts('stops')
                    ->badge()
                    ->alignCenter()
                    ->color(fn (int $state): string => $state < 2 ? 'danger' : 'gray')
                    ->tooltip(fn (int $state): ?string => $state < 2
                        ? 'A corridor carries a journey only between two towns on it, so this one carries nothing yet.'
                        : null),

                TextColumn::make('rides_count')
                    ->label('Rides')
                    ->counts('rides')
                    ->alignCenter()
                    ->tooltip('Rides ever published on this road. While there are any, it can be closed but not deleted.'),

                IconColumn::make('is_active')
                    ->label('Offered')
                    ->boolean(),

                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Offered to passengers')
                    ->placeholder('Any')
                    ->trueLabel('Open')
                    ->falseLabel('Closed'),
            ])
            ->recordActions([
                EditAction::make()->label('Open the road'),

                DeleteAction::make()
                    // `rides.travel_route_id` is restrictOnDelete, so this
                    // would fail at the database - and deleting a road with
                    // rides on it would take somebody's booking down too.
                    ->hidden(fn (TravelRoute $record): bool => TravelRouteResource::carriesRides($record))
                    ->modalDescription('The towns stay in the catalogue; they just stop being on this road.'),
            ]);
    }
}
