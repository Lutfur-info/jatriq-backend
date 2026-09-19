<?php

namespace App\Filament\Resources\Rides\Pages;

use App\Filament\Resources\Rides\RideResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Every ride on the system.
 *
 * No header actions: a ride is published by a driver from the app.
 */
class ListRides extends ListRecords
{
    protected static string $resource = RideResource::class;
}
