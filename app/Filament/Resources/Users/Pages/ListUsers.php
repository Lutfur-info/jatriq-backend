<?php

namespace App\Filament\Resources\Users\Pages;

use App\enum\VerificationStatus;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The queue, split by badge.
 *
 * "Awaiting review" opens first: it is the only tab with work in it, and it
 * is what the removed `GET /api/admin/verifications` used to return.
 */
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => $this->tabFor(VerificationStatus::Pending)
                ->label('Awaiting review'),
            'rejected' => $this->tabFor(VerificationStatus::Rejected),
            'verified' => $this->tabFor(VerificationStatus::Verified),
            'unverified' => $this->tabFor(VerificationStatus::Unverified)
                ->label('Not submitted'),
            'all' => Tab::make('Everyone'),
        ];
    }

    /**
     * A tab holding one badge, with the number waiting on it.
     */
    private function tabFor(VerificationStatus $status): Tab
    {
        return Tab::make($status->getLabel())
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('verification_status', $status->name))
            ->badge(UserResource::getEloquentQuery()->where('verification_status', $status->name)->count());
    }
}
