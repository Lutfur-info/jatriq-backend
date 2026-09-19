<?php

namespace App\enum;

/**
 * What an issued code entitles its holder to do.
 *
 * Codes are namespaced by purpose so one cannot be spent on the other: a
 * code sent to reset a password must not also verify the msisdn, which
 * would hand out an account activation to anyone who asked for a reset.
 */
enum OtpPurpose
{
    case MsisdnVerification;
    case PasswordReset;

    /**
     * The cache key segment the purpose's codes are held under.
     *
     * Verification keeps "msisdn" because that is the segment the codes
     * already in the cache were written with.
     */
    public function keySegment(): string
    {
        return match ($this) {
            self::MsisdnVerification => 'msisdn',
            self::PasswordReset => 'password-reset',
        };
    }

    /**
     * The names a client may refer to a purpose by.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Resolve a case from its name.
     *
     * @throws \Error when the name does not match a case.
     */
    public static function fromName(string $name): self
    {
        return constant(self::class.'::'.$name);
    }
}
