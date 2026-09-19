<?php

namespace App\Filament\Resources\Districts\Pages;

use App\Filament\Resources\Districts\DistrictResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * All 64 on one screen, created and edited in place.
 *
 * A district is a name and a switch, so a create page and an edit page would
 * each be one field on a page of their own.
 */
class ListDistricts extends ListRecords
{
    protected static string $resource = DistrictResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add a district')
                ->modalHeading('Add a district'),
        ];
    }
}
