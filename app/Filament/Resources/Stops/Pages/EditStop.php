<?php

namespace App\Filament\Resources\Stops\Pages;

use App\Filament\Resources\Stops\StopResource;
use App\Models\Stop;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * The town, and below it the corridors it sits on.
 */
class EditStop extends EditRecord
{
    protected static string $resource = StopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                // Same reason as the list: the rides table restricts the
                // delete, and retiring is what a used stop gets instead.
                ->hidden(fn (Stop $record): bool => StopResource::isReferencedByRide($record)),
        ];
    }
}
