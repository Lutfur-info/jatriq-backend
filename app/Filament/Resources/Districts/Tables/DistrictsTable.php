<?php

namespace App\Filament\Resources\Districts\Tables;

use App\Filament\Resources\Districts\DistrictResource;
use App\Models\District;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * All 64, alphabetical - the order somebody looks one up in.
 */
class DistrictsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('District')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stops_count')
                    ->label('Towns')
                    ->counts('stops')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->tooltip('Towns filed under this district. Most have none - the corridors only run through a few.'),

                IconColumn::make('is_active')
                    ->label('Offered')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Offered in the picker')
                    ->placeholder('Any')
                    ->trueLabel('Offered')
                    ->falseLabel('Retired'),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    // `stops.district_id` is nullOnDelete, so this would not
                    // fail - it would quietly blank the district on every
                    // town under it. Retire it instead.
                    ->hidden(fn (District $record): bool => DistrictResource::holdsStops($record)),
            ]);
    }
}
