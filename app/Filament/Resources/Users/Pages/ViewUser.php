<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * One applicant: their profile, their badge, and the documents to decide on.
 *
 * No header actions - a reviewer reads here and acts on the documents below.
 * Nothing about the profile is editable, and the badge cannot be set by hand.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;
}
