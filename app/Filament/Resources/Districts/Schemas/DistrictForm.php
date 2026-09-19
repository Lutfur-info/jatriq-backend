<?php

namespace App\Filament\Resources\Districts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * A district is a name and a switch. There is nothing else to get wrong,
 * which is the point of having taken it out of the stop.
 */
class DistrictForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('District')
                    ->required()
                    ->maxLength(255)
                    // Unique in the schema, and that uniqueness is the whole
                    // reason this table exists.
                    ->unique(ignoreRecord: true)
                    ->helperText('The official spelling - Cumilla, Chattogram, Bogura, Jashore, Barishal. Renaming re-labels every town under it at once.'),

                Toggle::make('is_active')
                    ->label('Offered in the picker')
                    ->default(true)
                    ->helperText('Turn off to retire one. The towns already filed under it keep it.'),
            ]);
    }
}
