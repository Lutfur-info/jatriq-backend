<?php

namespace App\Filament\Resources\Rides\Pages;

use App\Filament\Resources\Rides\RideResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * One ride: the trip, the seats, the driver and the passengers below.
 *
 * Editing is the one thing the panel does to a published ride, and it is
 * open even once passengers are aboard - see `EditRide` for why. There is
 * nothing else: a booking cannot be undone and a ride cannot be cancelled.
 */
class ViewRide extends ViewRecord
{
    protected static string $resource = RideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Correct this ride'),
        ];
    }
}
