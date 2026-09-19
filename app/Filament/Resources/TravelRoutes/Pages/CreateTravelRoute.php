<?php

namespace App\Filament\Resources\TravelRoutes\Pages;

use App\Filament\Resources\TravelRoutes\TravelRouteResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * A new road.
 *
 * It lands on the edit page, where the towns are: an empty corridor carries
 * no journey at all, so the list would be the wrong place to stop.
 */
class CreateTravelRoute extends CreateRecord
{
    protected static string $resource = TravelRouteResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Corridor added')
            ->body('Now lay the towns along it, starting from Dhaka.');
    }
}
