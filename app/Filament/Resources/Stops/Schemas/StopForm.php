<?php

namespace App\Filament\Resources\Stops\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * The place itself. Where it sits on a corridor is the relation manager's
 * job, because the same town sits on several at different positions.
 *
 * Nothing here points at a map any more: the coordinates went on 2026-09-18
 * and the Google place id on 2026-09-19, neither ever having been read by
 * anything. A town is a name, a district and a switch.
 *
 * The district is **picked, not typed** (2026-09-19). As free text the same
 * district could be spelled three ways and an admin had to remember how the
 * last town was filed; now the spelling is a row in `districts`.
 */
class StopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('The place')
                    ->description('One row per town in the country, shared by every corridor passing through it.')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Town')
                            ->required()
                            ->maxLength(255)
                            // Unique in the schema: Cumilla is one row on
                            // three corridors, never three rows.
                            ->unique(ignoreRecord: true)
                            ->helperText('What a passenger picks. Renaming it never rewrites a trip already published - rides carry a snapshot of the name.'),

                        Select::make('district_id')
                            ->label('District')
                            // Retired districts are still shown on the towns
                            // already filed under one, or editing such a town
                            // would silently clear it.
                            ->relationship(
                                'district',
                                'name',
                                fn (Builder $query): Builder => $query->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('District')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('districts', 'name'),
                            ])
                            ->createOptionModalHeading('Add a district')
                            ->helperText('The country has 64, all seeded. Only so two same-named towns read apart in a picker - never matched on.'),

                        Toggle::make('is_active')
                            ->label('Offered in the pickers')
                            ->default(true)
                            ->columnSpanFull()
                            ->helperText('Turn off to retire a town. Rides already published against it keep working.'),
                    ]),
            ]);
    }
}
