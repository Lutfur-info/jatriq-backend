<?php

namespace App\enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The verification badge shown against a user.
 *
 * This is derived from the user's documents by
 * `App\Services\VerificationService::refreshBadge()` and is never set by hand.
 *
 * The Filament contracts are here so the admin panel colours a badge from the
 * case itself; the API keeps sending the bare case name.
 */
enum VerificationStatus implements HasColor, HasLabel
{
    case Unverified;
    case Pending;
    case Verified;
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
     * How the badge reads to a reviewer.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Unverified => 'Not submitted',
            self::Pending => 'Awaiting review',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * The colour the panel paints the badge.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Unverified => 'gray',
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }
}
