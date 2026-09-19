<?php

namespace App\enum;

enum Role
{
    case Admin;
    case Driver;
    case Passenger;

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
     * The roles a visitor is allowed to register as.
     *
     * @return array<int, self>
     */
    public static function selfRegisterable(): array
    {
        return [self::Passenger, self::Driver];
    }
}
