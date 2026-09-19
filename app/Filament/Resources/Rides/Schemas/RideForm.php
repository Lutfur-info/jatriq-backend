<?php

namespace App\Filament\Resources\Rides\Schemas;

use App\Filament\Resources\Rides\RideResource;
use App\Models\Ride;
use App\Models\Stop;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * An admin's correction to a published ride.
 *
 * The driver's own edit closes the moment a seat sells. This one does not -
 * with no cancellation in the product, somebody has to be able to fix a
 * departure time on a ride that already has passengers in it. So the form
 * says plainly what a change will do to them rather than refusing it.
 *
 * The departure window a driver publishes inside (at least 15 minutes out,
 * at most 30 days ahead) is **not** applied here: an admin correcting the
 * record of a trip that has already run would be locked out by it.
 *
 * The two ends are stop ids, never free text, for the reason they are in the
 * API - a ride is searchable only from a known position on a known corridor.
 * `RideService::override()` re-resolves the corridor and both sequences from
 * whichever pair is saved.
 */
class RideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('booked_warning')
                    ->label('Passengers are already on this ride')
                    ->content(fn (Ride $record): string => sprintf(
                        '%d seat(s) booked. Changing the fare rewrites what every one of them owes, because a booking '
                        .'multiplies the ride\'s own seat price rather than storing a copy. Moving the route or the '
                        .'departure changes a trip they have already agreed to, and nobody is notified of any of it.',
                        RideResource::seatsBooked($record),
                    ))
                    ->columnSpanFull()
                    ->visible(fn (Ride $record): bool => RideResource::seatsBooked($record) > 0),

                Section::make('The trip')
                    ->description('Saving re-resolves the corridor and both positions along it from these two stops, exactly as publishing does.')
                    ->columns(2)
                    ->components([
                        Select::make('origin_stop_id')
                            ->label('From')
                            ->options(fn (Ride $record): array => self::stopOptions($record))
                            ->searchable()
                            ->required(),

                        Select::make('destination_stop_id')
                            ->label('To')
                            ->options(fn (Ride $record): array => self::stopOptions($record))
                            ->searchable()
                            ->required()
                            ->different('origin_stop_id'),

                        DateTimePicker::make('departs_at')
                            ->label('Departs')
                            ->seconds(false)
                            ->required()
                            ->helperText('An admin may set a departure in the past; a driver may not.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Seats and fare')
                    ->columns(2)
                    ->components([
                        TextInput::make('seat_price')
                            ->label('Seat fare')
                            ->prefix('BDT')
                            ->numeric()
                            ->required()
                            ->minValue((int) config('rides.price.min'))
                            ->maxValue((int) config('rides.price.max')),

                        TextInput::make('seats_offered')
                            ->label('Seats offered')
                            ->numeric()
                            ->required()
                            // Never below what passengers already hold, or
                            // the free-seat arithmetic every read runs goes
                            // negative. `RideService::override()` enforces it
                            // again under the row lock.
                            ->minValue(fn (Ride $record): int => max(1, RideResource::seatsBooked($record)))
                            ->maxValue(fn (Ride $record): int => $record->vehicle->seats)
                            ->helperText(fn (Ride $record): string => sprintf(
                                'Passenger seats, the driver\'s own excluded. %d booked, and the vehicle holds %d.',
                                RideResource::seatsBooked($record),
                                $record->vehicle->seats,
                            )),
                    ]),
            ]);
    }

    /**
     * The stops that may be picked, keyed by id.
     *
     * Offered stops, plus whichever two this ride already runs between - a
     * retired town stays valid on the rides published against it, so leaving
     * it out would silently move a ride the admin only meant to reprice.
     *
     * @return array<int, string>
     */
    private static function stopOptions(Ride $ride): array
    {
        $current = array_filter([$ride->origin_stop_id, $ride->destination_stop_id]);

        return Stop::query()
            ->where(fn (Builder $offered): Builder => $offered
                ->where('is_active', true)
                ->orWhereIn('id', $current))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
