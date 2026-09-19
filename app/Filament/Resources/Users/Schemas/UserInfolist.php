<?php

namespace App\Filament\Resources\Users\Schemas;

use App\enum\DocumentType;
use App\enum\Gender;
use App\enum\Role;
use App\Models\User;
use App\Services\VerificationService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * What a reviewer reads before deciding: the profile, then the badge it adds
 * up to, then the vehicle if the applicant drives.
 *
 * The documents themselves are the relation manager below this - that is
 * where a decision is recorded.
 */
class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->description('What the applicant told us when they registered.')
                    ->columns(3)
                    ->components([
                        TextEntry::make('full_name')->label('Name'),
                        // Role and Gender are pure enums with no label of
                        // their own - the case name is the label.
                        TextEntry::make('role')
                            ->badge()
                            ->formatStateUsing(fn (Role $state): string => $state->name)
                            ->color(fn (Role $state): string => $state === Role::Driver ? 'info' : 'gray'),
                        TextEntry::make('is_active')
                            ->label('Account')
                            ->badge()
                            ->state(fn (User $record): string => $record->is_active ? 'Active' : 'Suspended')
                            ->color(fn (User $record): string => $record->is_active ? 'success' : 'danger'),

                        TextEntry::make('msisdn')
                            ->label('Mobile')
                            ->state(fn (User $record): string => $record->dial_code.ltrim($record->msisdn, '0'))
                            ->copyable(),
                        TextEntry::make('msisdn_verified_at')
                            ->label('Mobile verified')
                            ->dateTime()
                            ->placeholder('Not verified'),
                        TextEntry::make('email')->placeholder('None given'),

                        TextEntry::make('gender')
                            ->formatStateUsing(fn (Gender $state): string => $state->name),
                        TextEntry::make('date_of_birth')
                            ->label('Date of birth')
                            ->placeholder('Not given'),
                        TextEntry::make('created_at')
                            ->label('Registered')
                            ->dateTime(),
                    ]),

                Section::make('Badge')
                    ->description('Derived from the documents below - never set by hand.')
                    ->columns(3)
                    ->components([
                        TextEntry::make('verification_status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('verified_at')
                            ->label('Verified at')
                            ->dateTime()
                            ->placeholder('Not verified'),
                        TextEntry::make('missing_documents')
                            ->label('Still missing')
                            ->badge()
                            ->color('gray')
                            ->placeholder('Nothing outstanding')
                            ->state(fn (User $record): array => array_map(
                                fn (DocumentType $type): string => $type->label(),
                                app(VerificationService::class)->missingFor($record),
                            )),
                    ]),

                Section::make('Vehicle')
                    ->description('Registered by the driver; not part of the badge.')
                    ->columns(4)
                    ->relationship('vehicle')
                    ->visible(fn (User $record): bool => $record->vehicle !== null)
                    ->components([
                        TextEntry::make('registration_number')->label('Vehicle number'),
                        TextEntry::make('model')
                            ->label('Type')
                            ->formatStateUsing(fn ($state): string => $state->label()),
                        TextEntry::make('cabin_class')
                            ->label('Class')
                            ->formatStateUsing(fn ($state): string => $state->label()),
                        TextEntry::make('seats')->label('Passenger seats'),
                    ]),
            ]);
    }
}
