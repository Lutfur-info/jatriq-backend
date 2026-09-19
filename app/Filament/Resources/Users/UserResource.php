<?php

namespace App\Filament\Resources\Users;

use App\enum\Role;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The applicants an admin verifies.
 *
 * Read only by design. Riders arrive through the registration API and an OTP,
 * so nobody is created here, and a reviewer's job is to read a profile and
 * decide on the documents behind it - not to edit either. The decision itself
 * lives on the documents relation manager.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $modelLabel = 'applicant';

    protected static ?string $pluralModelLabel = 'applicants';

    protected static ?string $navigationLabel = 'Verification';

    /**
     * Only riders are reviewed.
     *
     * `DocumentType::requiredFor(Role::Admin)` is empty, so an admin has no
     * documents and can never be badged - listing them here would be a row
     * that can never be acted on.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('role', [Role::Driver->name, Role::Passenger->name]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * Accounts are created by registering, never by a reviewer.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
