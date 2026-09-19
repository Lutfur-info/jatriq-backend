<?php

namespace App\enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a single uploaded document sits in the review queue.
 *
 * The Filament contracts are here so the admin panel colours a badge from the
 * case itself; the API keeps sending the bare case name.
 */
enum DocumentStatus implements HasColor, HasLabel
{
    case Pending;
    case Approved;
    case Rejected;

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
     * The outcomes a reviewer may record. A reviewer never sets Pending.
     *
     * @return array<int, self>
     */
    public static function reviewable(): array
    {
        return [self::Approved, self::Rejected];
    }

    /**
     * How the state reads to a reviewer.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * The colour the panel paints the badge.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
