<?php

namespace App\Filament\Resources\TravelRoutes\Pages;

use App\Filament\Resources\TravelRoutes\TravelRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * The roads out of Dhaka.
 */
class ListTravelRoutes extends ListRecords
{
    protected static string $resource = TravelRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a corridor'),
        ];
    }
}
