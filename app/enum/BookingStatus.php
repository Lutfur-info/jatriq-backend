<?php

namespace App\enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a passenger's seats sit in the driver's decision.
 *
 * A seat is **requested**, not taken: a passenger asks, and the driver who is
 * letting a stranger into their car says yes or no. Pending is where every
 * booking starts and the only state a passenger can put one in.
 *
 * A pending booking **holds its seats** exactly as a confirmed one does. The
 * alternative - counting only confirmed seats - lets a driver confirm more
 * requests than the car has, so the oversell would move from the booking to
 * the confirmation rather than going away. Declining is what frees a seat,
 * and it is the only thing that does.
 *
 * Like `Role` and `Gender` this is a **pure enum**, persisted by case name,
 * so adding a case needs a migration to alter the column. The Filament
 * contracts are here for the same reason `DocumentStatus` has them - the
 * panel colours a badge from the case - and the API keeps sending the bare
 * case name beside a human label.
 */
enum BookingStatus implements HasColor, HasLabel
{
    case Pending;
    case Confirmed;
    case Declined;

    /**
     * The values persisted in the database.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Resolve a case from its persisted name.
     *
     * @throws \Error when the name does not match a case.
     */
    public static function fromName(string $name): self
    {
        return constant(self::class.'::'.$name);
    }

    /**
     * The decisions a driver may record.
     *
     * A driver never sets Pending: that is where a request arrives, and the
     * passenger asking again is the only thing that puts one back.
     *
     * @return array<int, self>
     */
    public static function decidable(): array
    {
        return [self::Confirmed, self::Declined];
    }

    /**
     * The statuses whose seats are off the market.
     *
     * The single statement of which bookings count against `seats_offered`.
     * Every seat sum in the system is built from this - the repository's
     * total, the "not full" subquery behind the public board, and the panel's
     * columns - so a fourth case is added here and nowhere else.
     *
     * @return array<int, string>
     */
    public static function holdingNames(): array
    {
        return [self::Pending->name, self::Confirmed->name];
    }

    /**
     * Whether a booking in this state is holding its seats.
     */
    public function holdsSeats(): bool
    {
        return $this !== self::Declined;
    }

    /**
     * How the state reads to either side of the ride.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting the driver',
            self::Confirmed => 'Confirmed',
            self::Declined => 'Declined',
        };
    }

    /**
     * The colour the panel paints the badge.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Declined => 'danger',
        };
    }
}
