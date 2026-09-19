<?php

namespace App\enum;

/**
 * Whether the vehicle is air conditioned, which is what a fare is priced on.
 *
 * A pure enum storing the case name, matching
 * `$table->enum('cabin_class', CabinClass::names())`. Kept as an enum rather
 * than a boolean because a third class - a chair coach, say - is a case here
 * and a migration, not a second flag to reconcile with the first.
 */
enum CabinClass
{
    case Ac;
    case NonAc;

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
     * A human readable name for clients and validation messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ac => 'AC',
            self::NonAc => 'Non-AC',
        };
    }
}
