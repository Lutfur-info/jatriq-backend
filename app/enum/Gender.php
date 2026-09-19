<?php

namespace App\enum;

enum Gender
{
    case Male;
    case Female;

    /**
     * The values persisted in the database.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }
}
