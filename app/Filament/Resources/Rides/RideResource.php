<?php

namespace App\Filament\Resources\Rides;

use App\enum\BookingStatus;
use App\Filament\Resources\Rides\Pages\EditRide;
use App\Filament\Resources\Rides\Pages\ListRides;
use App\Filament\Resources\Rides\Pages\ViewRide;
use App\Filament\Resources\Rides\RelationManagers\BookingsRelationManager;
use App\Filament\Resources\Rides\Schemas\RideForm;
use App\Filament\Resources\Rides\Schemas\RideInfolist;
use App\Filament\Resources\Rides\Tables\RidesTable;
use App\Models\Booking;
use App\Models\Ride;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every ride a driver has published, and the seats sold on it.
 *
 * Nothing is created here: a ride is published by a driver through
 * `POST /api/driver/rides` and a booking made by a passenger. Nor deleted -
 * there is no cancellation anywhere in the product and `bookings.ride_id`
 * would take a passenger's seats with it, so retiring a ride means letting
 * it depart.
 *
 * An admin **may edit any ride**, including one that is already booked, and
 * that is the one place the panel overrules a rider: the driver's own edit
 * closes the moment a seat sells, and with nothing able to cancel, somebody
 * has to be able to put a wrong departure right. `RideService::override()`
 * is where the remaining rules live - see `EditRide`.
 *
 * Unlike `UserResource` this is not a queue and nothing here waits on an
 * admin: it is the operational picture - who is going where, when, and how
 * full the vehicle is.
 */
class RideResource extends Resource
{
    protected static ?string $model = Ride::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $recordTitleAttribute = 'origin_label';

    protected static ?string $modelLabel = 'ride';

    protected static ?string $pluralModelLabel = 'rides';

    protected static ?string $navigationLabel = 'Rides';

    protected static ?int $navigationSort = 1;

    /**
     * Every ride, with the seat sum the whole screen is about.
     *
     * `withSum` is not optional here. Seats free is `seats_offered` minus the
     * seats booked, and a ride rendered without the aggregate would report an
     * empty vehicle - the same trap `RideResource` guards against in the API.
     * It counts the **holding** bookings only: a pending request holds its
     * seat exactly as a confirmed one does, and a declined one gave it back.
     * The relations are eager loaded because the table renders the driver,
     * the car and the corridor on every row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'vehicle', 'travelRoute'])
            ->withSum(['bookings' => self::holdingSeats(...)], 'seats')
            ->withCount([
                'bookings',
                'bookings as pending_bookings_count' => self::stillWaiting(...),
            ]);
    }

    /**
     * The bookings a seat sum counts: everything but a declined one.
     *
     * @param  Builder<Booking>  $bookings
     * @return Builder<Booking>
     */
    private static function holdingSeats(Builder $bookings): Builder
    {
        return $bookings->holding();
    }

    /**
     * The requests the driver has not answered.
     *
     * @param  Builder<Booking>  $bookings
     * @return Builder<Booking>
     */
    private static function stillWaiting(Builder $bookings): Builder
    {
        return $bookings->where('status', BookingStatus::Pending->name);
    }

    /**
     * The seats passengers are holding on this ride.
     *
     * Pending and confirmed alike - a request holds its seat until the driver
     * declines it. Reads the aggregate `getEloquentQuery()` selected, and
     * falls back to counting rather than answering zero: a wrong zero here
     * reads as an empty vehicle, which is the one mistake worth a query.
     */
    public static function seatsBooked(Ride $ride): int
    {
        $booked = $ride->getAttribute('bookings_sum_seats');

        return $booked === null
            ? (int) $ride->bookings()->holding()->sum('seats')
            : (int) $booked;
    }

    /**
     * How many requests are still waiting on the driver.
     */
    public static function requestsWaiting(Ride $ride): int
    {
        $waiting = $ride->getAttribute('pending_bookings_count');

        return $waiting === null
            ? $ride->bookings()->where('status', BookingStatus::Pending->name)->count()
            : (int) $waiting;
    }

    /**
     * The seats still on sale, never below zero.
     */
    public static function seatsAvailable(Ride $ride): int
    {
        return max(0, $ride->seats_offered - static::seatsBooked($ride));
    }

    public static function form(Schema $schema): Schema
    {
        return RideForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RideInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RidesTable::configure($table);
    }

    /**
     * Rides are published by drivers, never by a reviewer.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            BookingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRides::route('/'),
            'view' => ViewRide::route('/{record}'),
            'edit' => EditRide::route('/{record}/edit'),
        ];
    }
}
