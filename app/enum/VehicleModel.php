<?php

namespace App\enum;

/**
 * The vehicles a driver may register.
 *
 * A pure enum storing the case name, matching
 * `$table->enum('model', VehicleModel::names())` - so adding a model here
 * needs a migration to alter the column, exactly like Role and Gender.
 */
enum VehicleModel
{
    case HiAce;
    case Noah;
    case Corolla;

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
     * How the model is spelled for a passenger reading it.
     */
    public function label(): string
    {
        return match ($this) {
            self::HiAce => 'Toyota HiAce',
            self::Noah => 'Toyota Noah',
            self::Corolla => 'Toyota Corolla',
        };
    }

    /**
     * The passenger-seat count a driver is offered as the starting point.
     *
     * The driver's own seat is excluded, so these are one below the seat
     * totals the models are sold with (a 12-seat HiAce carries 11).
     *
     * A microbus is sold in several seating layouts and an owner may have had
     * one refitted, so this is a default the driver can correct - never a
     * validation rule.
     */
    public function typicalSeats(): int
    {
        return match ($this) {
            self::HiAce => 11,
            self::Noah => 6,
            self::Corolla => 3,
        };
    }
}
