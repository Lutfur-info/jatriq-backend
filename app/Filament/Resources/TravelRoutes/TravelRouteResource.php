<?php

namespace App\Filament\Resources\TravelRoutes;

use App\Filament\Resources\TravelRoutes\Pages\CreateTravelRoute;
use App\Filament\Resources\TravelRoutes\Pages\EditTravelRoute;
use App\Filament\Resources\TravelRoutes\Pages\ListTravelRoutes;
use App\Filament\Resources\TravelRoutes\RelationManagers\StopsRelationManager;
use App\Filament\Resources\TravelRoutes\Schemas\TravelRouteForm;
use App\Filament\Resources\TravelRoutes\Tables\TravelRoutesTable;
use App\Models\Ride;
use App\Models\TravelRoute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The highway corridors, as roads an admin can lay out.
 *
 * A corridor is an ordered list of towns, and the order is the whole of how
 * direction works - sequence runs outbound from Dhaka on every one of them,
 * so a ride toward Dhaka is one running down the numbers. Editing that order
 * is therefore the dangerous operation on this screen, and the reason the
 * stops relation manager asks "where on the road?" instead of "what number?".
 *
 * Corridors, not divisions: the Noakhali branch carries Laksam and Sonaimuri
 * and is not the Dhaka - Chattogram highway. A new road is a new corridor
 * here, not a bigger one.
 */
class TravelRouteResource extends Resource
{
    protected static ?string $model = TravelRoute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'corridor';

    protected static ?string $pluralModelLabel = 'corridors';

    protected static ?string $navigationLabel = 'Corridors';

    protected static string|UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return TravelRouteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TravelRoutesTable::configure($table);
    }

    /**
     * Whether any ride is published on this corridor.
     *
     * `rides.travel_route_id` is `restrictOnDelete`, so such a corridor
     * cannot be deleted - and should not be, because deleting it would take
     * the rides and somebody's booking with it. Retire it with `is_active`.
     */
    public static function carriesRides(TravelRoute $route): bool
    {
        return Ride::query()
            ->where('travel_route_id', $route->getKey())
            ->exists();
    }

    public static function getRelations(): array
    {
        return [
            StopsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTravelRoutes::route('/'),
            'create' => CreateTravelRoute::route('/create'),
            'edit' => EditTravelRoute::route('/{record}/edit'),
        ];
    }
}
