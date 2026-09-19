<?php

namespace App\Filament\Resources\Users\Tables;

use App\enum\Role;
use App\enum\VerificationStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The review queue.
 *
 * Ordered oldest waiting first, because an applicant who submitted on Monday
 * should not sit behind one who submitted this morning.
 */
class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Applicant')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name']),

                TextColumn::make('msisdn')
                    ->label('Mobile')
                    ->searchable(),

                TextColumn::make('role')
                    ->badge()
                    // A pure enum with no label of its own: the case name is
                    // the label, so it has to be spelled out for rendering.
                    ->formatStateUsing(fn (Role $state): string => $state->name)
                    ->color(fn (Role $state): string => $state === Role::Driver ? 'info' : 'gray'),

                TextColumn::make('verification_status')
                    ->label('Badge')
                    ->badge()
                    ->sortable(),

                TextColumn::make('documents_count')
                    ->label('Documents')
                    ->counts('documents')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Last activity')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'asc')
            ->filters([
                SelectFilter::make('verification_status')
                    ->label('Badge')
                    ->options(
                        collect(VerificationStatus::cases())
                            ->mapWithKeys(fn (VerificationStatus $status): array => [
                                $status->name => $status->getLabel(),
                            ])
                            ->all(),
                    ),

                SelectFilter::make('role')
                    ->options([
                        Role::Driver->name => 'Driver',
                        Role::Passenger->name => 'Passenger',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Review'),
            ]);
    }
}
