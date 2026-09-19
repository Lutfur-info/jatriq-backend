<?php

namespace App\Filament\Resources\Rides\RelationManagers;

use App\enum\BookingStatus;
use App\Filament\Resources\Rides\RideResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Booking;
use App\Models\Ride;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who is in the vehicle: one row per passenger, in the order they booked.
 *
 * There is at most one booking per passenger per ride - booking again tops
 * up the same row - so `seats` is a running total and this table is the
 * passenger list, not a log of individual acts of booking.
 *
 * Nothing here writes, and the **decision is the driver's** - they are the
 * one letting a stranger into their car, and they answer through
 * `PATCH /api/driver/bookings/{booking}`. A booking is made by a passenger
 * through `POST /api/rides/{ride}/bookings`, there is no cancellation
 * anywhere in the product, and deleting one from the panel would silently
 * free a seat the passenger still believes is theirs.
 */
class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Passengers';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedTicket;

    /**
     * The seats taken, as a badge on the tab.
     */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Ride $ownerRecord */
        $seats = (int) $ownerRecord->bookings()->holding()->sum('seats');

        return $seats === 0 ? null : (string) $seats;
    }

    /**
     * Amber while anybody is still waiting on the driver.
     */
    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Ride $ownerRecord */
        return RideResource::requestsWaiting($ownerRecord) > 0 ? 'warning' : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->description('A seat is asked for, not taken: the driver confirms or declines from their app. A pending request holds its seats just as a confirmed one does - only a decline gives them back.')
            // `totalAmount()` multiplies the ride's own fare, so the ride has
            // to be in hand or every row reads it back one at a time.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['ride', 'user']))
            ->columns([
                TextColumn::make('user.full_name')
                    ->label('Passenger')
                    ->description(fn (Booking $record): string => $record->user->msisdn)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('user', fn (Builder $passenger): Builder => $passenger
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('msisdn', 'like', "%{$search}%"))),

                TextColumn::make('status')
                    ->label('Driver\'s answer')
                    ->badge()
                    ->description(fn (Booking $record): ?string => $record->holdsSeats()
                        ? null
                        : 'Seats back on sale')
                    ->sortable(),

                TextColumn::make('user.verification_status')
                    ->label('Badge')
                    ->badge(),

                TextColumn::make('seats')
                    ->label('Seats')
                    ->badge()
                    ->color('gray')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Fare')
                    ->state(fn (Booking $record): string => $record->totalAmount())
                    ->money('BDT'),

                TextColumn::make('rating')
                    ->label('Her rating')
                    ->badge()
                    ->alignCenter()
                    ->placeholder(fn (Booking $record): string => $record->isRateable()
                        ? 'Not rated'
                        : '—')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state))
                    ->color(fn (int $state): string => $state >= 4 ? 'success' : 'warning'),

                TextColumn::make('created_at')
                    ->label('Asked')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('decided_at')
                    ->label('Answered')
                    ->dateTime()
                    ->placeholder('Still waiting')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Driver\'s answer')
                    ->options(
                        collect(BookingStatus::cases())
                            ->mapWithKeys(fn (BookingStatus $status): array => [
                                $status->name => $status->getLabel(),
                            ])
                            ->all(),
                    ),
            ])
            ->emptyStateHeading('Nobody has asked for a seat yet')
            ->emptyStateDescription('Every seat the driver offered is still on sale.')
            ->recordActions([
                Action::make('passenger')
                    ->label('Open passenger')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Booking $record): string => UserResource::getUrl('view', ['record' => $record->user_id])),
            ]);
    }
}
