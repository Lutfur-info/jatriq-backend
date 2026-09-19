<?php

namespace App\Filament\Resources\TravelRoutes\Pages;

use App\Filament\Resources\TravelRoutes\TravelRouteResource;
use App\Models\TravelRoute;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * The corridor, and below it the road.
 */
class EditTravelRoute extends EditRecord
{
    protected static string $resource = TravelRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                // Rides restrict the delete; a road in use is closed with
                // is_active, not removed.
                ->hidden(fn (TravelRoute $record): bool => TravelRouteResource::carriesRides($record)),
        ];
    }
}
