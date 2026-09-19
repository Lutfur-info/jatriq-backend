<?php

namespace App\Filament\Resources\Stops\Tables;

use App\Filament\Resources\Stops\StopResource;
use App\Models\Stop;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The catalogue of towns, alphabetical because that is how somebody looks
 * one up - there is no queue here and nothing waits on anybody.
 *
 * The corridor count is the column that matters: a stop on none of them is
 * invisible to every picker and every search, which is the one way adding a
 * town can silently do nothing.
 */
class StopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Town')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('district.name')
                    ->label('District')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Not filed'),

                TextColumn::make('travel_routes_count')
                    ->label('Corridors')
                    ->counts('travelRoutes')
                    ->badge()
                    ->alignCenter()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray')
                    ->tooltip(fn (int $state): ?string => $state === 0
                        ? 'On no corridor, so no passenger can search for it. Open it and attach a corridor.'
                        : null),

                IconColumn::make('is_active')
                    ->label('Offered')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Offered in the pickers')
                    ->placeholder('Any')
                    ->trueLabel('Offered')
                    ->falseLabel('Retired'),

                SelectFilter::make('district')
                    ->relationship('district', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('unplaced')
                    ->label('On no corridor')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('travelRoutes')),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    // The rides table restricts both stop columns on delete,
                    // so this would fail at the database anyway. Retiring is
                    // the answer once a ride has been published from here.
                    ->hidden(fn (Stop $record): bool => StopResource::isReferencedByRide($record))
                    ->modalDescription('It is detached from every corridor it is on. Rides already published are unaffected - none point at this stop.'),
            ]);
    }
}
