<?php

namespace App\Filament\Resources\Stops;

use App\Filament\Resources\Stops\Pages\CreateStop;
use App\Filament\Resources\Stops\Pages\EditStop;
use App\Filament\Resources\Stops\Pages\ListStops;
use App\Filament\Resources\Stops\RelationManagers\TravelRoutesRelationManager;
use App\Filament\Resources\Stops\Schemas\StopForm;
use App\Filament\Resources\Stops\Tables\StopsTable;
use App\Models\Ride;
use App\Models\Stop;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The towns a vehicle can be boarded or left at.
 *
 * Until now `TravelRouteSeeder` was the only way one existed, so putting a
 * town on the network meant editing a seeder and re-running it. This is that
 * job done by hand: create the place, then attach it to each corridor it sits
 * on at its position along that road (the relation manager below).
 *
 * A stop is nothing on its own - it reaches a picker only through a corridor,
 * so a stop with no corridors is flagged in the table rather than left to be
 * discovered when nobody can search for it.
 *
 * Retiring beats deleting: `is_active` hides a stop from the pickers while
 * the rides already published against it keep working, and the rides table
 * holds `restrictOnDelete` on both stop columns anyway.
 */
class StopResource extends Resource
{
    protected static ?string $model = Stop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'stop';

    protected static ?string $pluralModelLabel = 'stops';

    protected static ?string $navigationLabel = 'Stops';

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return StopForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StopsTable::configure($table);
    }

    /**
     * Whether any published ride starts or ends here.
     *
     * Such a stop can be edited and retired but never deleted: the rides
     * table restricts the delete at the database, and a ride losing its
     * origin would take somebody's booking with it.
     */
    public static function isReferencedByRide(Stop $stop): bool
    {
        return Ride::query()
            ->where('origin_stop_id', $stop->getKey())
            ->orWhere('destination_stop_id', $stop->getKey())
            ->exists();
    }

    public static function getRelations(): array
    {
        return [
            TravelRoutesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStops::route('/'),
            'create' => CreateStop::route('/create'),
            'edit' => EditStop::route('/{record}/edit'),
        ];
    }
}
