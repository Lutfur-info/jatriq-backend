<?php

namespace App\Filament\Resources\Rides\Tables;

use App\enum\BookingStatus;
use App\Filament\Resources\Rides\RideResource;
use App\Models\Ride;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every ride there is, latest departure first.
 *
 * Deliberately unfiltered by default: this is the whole board, departed
 * trips included, because an admin looking a ride up is usually looking up
 * one that has already run. The departure filter is how you narrow it to
 * what is still to come.
 *
 * "Free" is the column that carries the meaning - `seats_offered` minus the
 * seats booked. It is computed from the sum `RideResource::getEloquentQuery()`
 * selects, so it is a state column and therefore not sortable; the two
 * filters below answer the questions a sort would.
 */
class RidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('departs_at')
                    ->label('Departs')
                    ->dateTime()
                    ->description(fn (Ride $record): string => $record->departs_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('origin_label')
                    ->label('From')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('destination_label')
                    ->label('To')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('travelRoute.name')
                    ->label('Corridor')
                    // Rides published before corridors existed carry no road,
                    // and can never match a search. Worth seeing, not fixing.
                    ->placeholder('Off the network')
                    ->toggleable(),

                TextColumn::make('user.full_name')
                    ->label('Driver')
                    ->description(fn (Ride $record): string => $record->user->msisdn)
                    // `full_name` is an accessor, so the search has to name
                    // the columns behind it - as `UsersTable` does.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('user', fn (Builder $driver): Builder => $driver
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('msisdn', 'like', "%{$search}%"))),

                TextColumn::make('user.rating_average')
                    ->label('Driver rating')
                    ->badge()
                    ->alignCenter()
                    // Null is "nobody has rated him", which is not nought.
                    ->placeholder('Unrated')
                    ->formatStateUsing(fn (string $state, Ride $record): string => sprintf(
                        '%s (%d)',
                        $state,
                        $record->user->ratings_count,
                    ))
                    ->color(fn (string $state): string => match (true) {
                        (float) $state >= 4.0 => 'success',
                        (float) $state >= 3.0 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('vehicle.registration_number')
                    ->label('Vehicle')
                    ->searchable(),

                TextColumn::make('seat_price')
                    ->label('Seat fare')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('seats_offered')
                    ->label('Offered')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('seats_booked')
                    ->label('Held')
                    ->state(fn (Ride $record): int => RideResource::seatsBooked($record))
                    ->badge()
                    ->color('gray')
                    ->tooltip('Seats spoken for, pending requests included - only a decline gives one back.')
                    ->alignCenter(),

                TextColumn::make('pending_bookings_count')
                    ->label('Waiting')
                    ->state(fn (Ride $record): int => RideResource::requestsWaiting($record))
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'gray' : 'warning')
                    ->tooltip(fn (int $state): ?string => $state === 0
                        ? null
                        : 'Requests the driver has not answered yet.')
                    ->alignCenter(),

                TextColumn::make('seats_available')
                    ->label('Free')
                    ->state(fn (Ride $record): int => RideResource::seatsAvailable($record))
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'success')
                    ->tooltip(fn (int $state): ?string => $state === 0 ? 'Fully booked.' : null)
                    ->alignCenter(),
            ])
            ->defaultSort('departs_at', 'desc')
            ->filters([
                TernaryFilter::make('departure')
                    ->label('Departure')
                    ->placeholder('All rides')
                    ->trueLabel('Yet to depart')
                    ->falseLabel('Already departed')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('departs_at', '>=', now()),
                        false: fn (Builder $query): Builder => $query->where('departs_at', '<', now()),
                        blank: fn (Builder $query): Builder => $query,
                    ),

                TernaryFilter::make('seats')
                    ->label('Seats')
                    ->placeholder('Any')
                    ->trueLabel('Seats still free')
                    ->falseLabel('Fully booked')
                    /*
                     * The same correlated subquery the bookable list runs on,
                     * rather than a HAVING over the withSum alias - which
                     * would need a GROUP BY over every selected column. Each
                     * one is written out whole because `whereRaw` takes a
                     * literal, and a built-up string is how SQL gets away.
                     *
                     * A pending request holds its seat, so both statuses
                     * count - that list is `BookingStatus::holdingNames()`,
                     * and a new case has to be added here by hand.
                     */
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereRaw(
                            "(select coalesce(sum(bookings.seats), 0) from bookings where bookings.ride_id = rides.id and bookings.status in ('Pending', 'Confirmed')) < rides.seats_offered"
                        ),
                        false: fn (Builder $query): Builder => $query->whereRaw(
                            "(select coalesce(sum(bookings.seats), 0) from bookings where bookings.ride_id = rides.id and bookings.status in ('Pending', 'Confirmed')) >= rides.seats_offered"
                        ),
                        blank: fn (Builder $query): Builder => $query,
                    ),

                SelectFilter::make('travel_route_id')
                    ->label('Corridor')
                    ->relationship('travelRoute', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('booked')
                    ->label('Has bookings')
                    ->query(fn (Builder $query): Builder => $query->whereHas('bookings')),

                Filter::make('waiting')
                    ->label('Waiting on the driver')
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'bookings',
                        fn (Builder $pending): Builder => $pending->where('status', BookingStatus::Pending->name),
                    )),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),

                // Open even on a booked ride, which the driver's own edit is
                // not. There is no cancellation, so this is the only way a
                // wrong departure ever gets put right.
                EditAction::make()->label('Correct'),
            ]);
    }
}
