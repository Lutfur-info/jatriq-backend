<?php

namespace App\Filament\Resources\Stops\Pages;

use App\Filament\Resources\Stops\StopResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Adding a town.
 *
 * It lands on the edit page rather than the list, because a stop on no
 * corridor is invisible to every picker - the corridors table there is the
 * rest of the job.
 */
class CreateStop extends CreateRecord
{
    protected static string $resource = StopResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Stop added')
            ->body('Attach it to every corridor it sits on - until then nobody can search for it.');
    }
}
