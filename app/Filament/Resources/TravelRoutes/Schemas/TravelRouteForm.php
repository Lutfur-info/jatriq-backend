<?php

namespace App\Filament\Resources\TravelRoutes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * The road itself. The towns along it are the relation manager.
 */
class TravelRouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('The corridor')
                    ->description('Name it for the road, not the division: "Dhaka - Noakhali" is its own corridor even though it leaves the Chattogram highway at Cumilla.')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            // Only while creating: see the slug's note.
                            ->afterStateUpdated(function (string $operation, Set $set, ?string $state): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            })
                            ->helperText('"Dhaka - Cox\'s Bazar".'),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            // Written once from the name and frozen after
                            // that: `TravelRouteSeeder` matches corridors on
                            // the slug, so a slug that followed a rename
                            // would make the next seed create a second
                            // corridor rather than update this one.
                            ->disabled()
                            ->dehydrated()
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? 'Taken from the name.'
                                : 'Fixed for the life of the corridor - the seeder matches on it. Renaming is safe; this stays.'),

                        Toggle::make('is_active')
                            ->label('Offered to passengers')
                            ->default(true)
                            ->helperText('Turn off to close a road. Rides already published on it keep their pages; nothing new can be published or searched along it.'),
                    ]),
            ]);
    }
}
