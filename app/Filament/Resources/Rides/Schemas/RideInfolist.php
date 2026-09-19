<?php

namespace App\Filament\Resources\Rides\Schemas;

use App\Filament\Resources\Rides\RideResource;
use App\Models\Ride;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One ride in full: the trip, the seats sold on it, who is driving and what
 * they are driving.
 *
 * The passengers are the relation manager below - a ride is only interesting
 * because somebody is in it.
 */
class RideInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('The trip')
                    ->description('The labels are a snapshot taken when the ride was published, so renaming a town never rewrites a trip somebody already agreed to.')
                    ->columns(3)
                    ->components([
                        TextEntry::make('origin_label')->label('From'),
                        TextEntry::make('destination_label')->label('To'),
                        TextEntry::make('departs_at')
                            ->label('Departs')
                            ->dateTime()
                            ->badge()
                            ->color(fn (Ride $record): string => $record->departs_at->isFuture() ? 'success' : 'gray'),

                        TextEntry::make('travelRoute.name')
                            ->label('Corridor')
                            ->placeholder('Off the network - it can never match a search'),
                        TextEntry::make('direction')
                            ->label('Direction')
                            // Direction is not a column: sequence runs
                            // outbound from Dhaka on every corridor, so a
                            // ride toward Dhaka is one running down them.
                            ->state(fn (Ride $record): ?string => self::direction($record))
                            ->placeholder('Unknown - the ride has no sequences'),
                        TextEntry::make('created_at')
                            ->label('Published')
                            ->dateTime(),
                    ]),

                Section::make('Seats and fare')
                    ->description('The fare is flat: a passenger joining halfway pays the same as one who boarded at the start.')
                    ->columns(4)
                    ->components([
                        TextEntry::make('seat_price')
                            ->label('Seat fare')
                            ->money('BDT'),
                        TextEntry::make('seats_offered')->label('Seats offered'),
                        TextEntry::make('seats_booked')
                            ->label('Seats held')
                            ->badge()
                            ->color('gray')
                            ->helperText('Pending requests included - only a decline gives a seat back.')
                            ->state(fn (Ride $record): int => RideResource::seatsBooked($record)),
                        TextEntry::make('seats_available')
                            ->label('Seats free')
                            ->badge()
                            ->state(fn (Ride $record): int => RideResource::seatsAvailable($record))
                            ->color(fn (int $state): string => $state === 0 ? 'danger' : 'success'),

                        TextEntry::make('requests_waiting')
                            ->label('Waiting on the driver')
                            ->badge()
                            ->state(fn (Ride $record): int => RideResource::requestsWaiting($record))
                            ->color(fn (int $state): string => $state === 0 ? 'gray' : 'warning'),

                        TextEntry::make('takings')
                            ->label('Held so far')
                            // Fixed point, like `Booking::totalAmount()` -
                            // a taka amount is never a binary float.
                            ->state(fn (Ride $record): string => 'BDT '.bcmul(
                                $record->seat_price,
                                (string) RideResource::seatsBooked($record),
                                2,
                            ))
                            ->helperText('Seats held at the ride\'s own fare, pending requests included. Nothing here says anybody has paid.'),
                    ]),

                Section::make('Driver')
                    ->description('Whose vehicle the seats are in, and what passengers made of it.')
                    ->columns(5)
                    ->relationship('user')
                    ->components([
                        TextEntry::make('full_name')->label('Name'),
                        TextEntry::make('msisdn')
                            ->label('Mobile')
                            ->state(fn (User $record): string => $record->dial_code.ltrim($record->msisdn, '0'))
                            ->copyable(),
                        TextEntry::make('verification_status')
                            ->label('Badge')
                            ->badge(),
                        TextEntry::make('rating_average')
                            ->label('Rating')
                            ->badge()
                            ->placeholder('Not rated yet')
                            ->formatStateUsing(fn (string $state, User $record): string => sprintf(
                                '%s from %d trip%s',
                                $state,
                                $record->ratings_count,
                                $record->ratings_count === 1 ? '' : 's',
                            ))
                            ->color(fn (string $state): string => (float) $state >= 4.0 ? 'success' : 'warning'),
                        TextEntry::make('is_active')
                            ->label('Account')
                            ->badge()
                            ->state(fn (User $record): string => $record->is_active ? 'Active' : 'Suspended')
                            ->color(fn (User $record): string => $record->is_active ? 'success' : 'danger'),
                    ]),

                Section::make('Vehicle')
                    ->description('Seats are passenger seats - the driver\'s own is not counted.')
                    ->columns(4)
                    ->relationship('vehicle')
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

    /**
     * Which way along the corridor the vehicle is running.
     *
     * Null for a ride published before corridors existed, which carries no
     * sequences at all.
     */
    private static function direction(Ride $ride): ?string
    {
        if ($ride->origin_sequence === null || $ride->destination_sequence === null) {
            return null;
        }

        return $ride->destination_sequence < $ride->origin_sequence
            ? 'Toward Dhaka'
            : 'Outbound from Dhaka';
    }
}
