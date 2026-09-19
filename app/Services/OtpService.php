<?php

namespace App\Services;

use App\enum\OtpPurpose;
use App\enum\OtpStatus;
use App\Models\User;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Issues and verifies the one time codes sent to a user's msisdn.
 *
 * Codes live in the cache (Redis in production) rather than the database:
 * they are short lived, write heavy, and expire on their own.
 *
 * Every code is issued for one purpose and is only ever read back under that
 * purpose, so a msisdn can hold a verification code and a password reset code
 * at the same time without either standing in for the other.
 *
 * @phpstan-type OtpRecord array{code: string, attempts: int, expires_at: int}
 */
class OtpService
{
    public function __construct(private CacheFactory $cache) {}

    /**
     * Issue a fresh code for the user and deliver it to their msisdn.
     *
     * Any previously issued code for the same purpose is replaced, so only
     * the newest one works.
     */
    public function send(User $user, OtpPurpose $purpose = OtpPurpose::MsisdnVerification): string
    {
        $code = $this->generateCode();
        $expiresAt = now()->addSeconds($this->ttl());

        $this->store()->put($this->codeKey($user->msisdn, $purpose), [
            'code' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        if ($this->exposesCodes()) {
            $this->store()->put($this->plainKey($user->msisdn, $purpose), $code, $expiresAt);
        }

        $this->startResendCooldown($user->msisdn, $purpose);

        $this->deliver($user, $code, $purpose);

        return $code;
    }

    /**
     * Check a submitted code against the one held for the msisdn.
     *
     * A valid code is consumed, so it cannot be replayed.
     */
    public function verify(
        string $msisdn,
        string $code,
        OtpPurpose $purpose = OtpPurpose::MsisdnVerification,
    ): OtpStatus {
        $key = $this->codeKey($msisdn, $purpose);

        /** @var OtpRecord|null $record */
        $record = $this->store()->get($key);

        if (! is_array($record)) {
            return OtpStatus::Expired;
        }

        if ($record['attempts'] >= $this->maxAttempts()) {
            $this->forget($msisdn, $purpose);

            return OtpStatus::TooManyAttempts;
        }

        if (! Hash::check($code, $record['code'])) {
            $record['attempts']++;

            $this->store()->put($key, $record, $this->secondsUntil($record['expires_at']));

            return $record['attempts'] >= $this->maxAttempts()
                ? OtpStatus::TooManyAttempts
                : OtpStatus::Invalid;
        }

        $this->forget($msisdn, $purpose);

        return OtpStatus::Valid;
    }

    /**
     * Whether issued codes may be read back with issuedCode().
     *
     * Production is excluded whatever the configuration says.
     */
    public function exposesCodes(): bool
    {
        return (bool) config('otp.expose_codes') && ! app()->isProduction();
    }

    /**
     * The plain code currently held for the msisdn, if one is readable.
     *
     * Verification always compares the submission against the hash; this reads
     * the mirror written by send(), so the returned code is the live one and
     * nothing about the record changes - no attempt is spent, no cooldown
     * started, and the code stays usable until it is verified or expires.
     *
     * @return array{code: string, expires_in: int}|null
     */
    public function issuedCode(string $msisdn, OtpPurpose $purpose = OtpPurpose::MsisdnVerification): ?array
    {
        if (! $this->exposesCodes()) {
            return null;
        }

        /** @var OtpRecord|null $record */
        $record = $this->store()->get($this->codeKey($msisdn, $purpose));
        $code = $this->store()->get($this->plainKey($msisdn, $purpose));

        if (! is_array($record) || ! is_string($code)) {
            return null;
        }

        return [
            'code' => $code,
            'expires_in' => $this->secondsUntil($record['expires_at']),
        ];
    }

    /**
     * Determine whether a new code may be sent to the msisdn yet.
     */
    public function canResend(string $msisdn, OtpPurpose $purpose = OtpPurpose::MsisdnVerification): bool
    {
        return $this->secondsUntilResend($msisdn, $purpose) === 0;
    }

    /**
     * The number of seconds left before another code may be requested.
     */
    public function secondsUntilResend(string $msisdn, OtpPurpose $purpose = OtpPurpose::MsisdnVerification): int
    {
        $availableAt = $this->store()->get($this->cooldownKey($msisdn, $purpose));

        return is_int($availableAt) ? $this->secondsUntil($availableAt) : 0;
    }

    /**
     * Discard the code and cooldown held for the msisdn.
     */
    public function forget(string $msisdn, OtpPurpose $purpose = OtpPurpose::MsisdnVerification): void
    {
        $this->store()->forget($this->codeKey($msisdn, $purpose));
        $this->store()->forget($this->plainKey($msisdn, $purpose));
        $this->store()->forget($this->cooldownKey($msisdn, $purpose));
    }

    /**
     * Hand the code to the user.
     *
     * @todo Replace the log line with the SMS provider once it is available.
     */
    private function deliver(User $user, string $code, OtpPurpose $purpose): void
    {
        Log::info('One time code issued.', [
            'msisdn' => $user->msisdn,
            'purpose' => $purpose->name,
            'code' => app()->isProduction() ? '******' : $code,
        ]);
    }

    /**
     * Block further sends to the msisdn for the configured cooldown.
     */
    private function startResendCooldown(string $msisdn, OtpPurpose $purpose): void
    {
        $availableAt = now()->addSeconds($this->resendCooldown());

        $this->store()->put(
            $this->cooldownKey($msisdn, $purpose),
            $availableAt->getTimestamp(),
            $availableAt,
        );
    }

    /**
     * Build a zero padded numeric code of the configured length.
     */
    private function generateCode(): string
    {
        $length = $this->length();

        return str_pad(
            (string) random_int(0, (10 ** $length) - 1),
            $length,
            '0',
            STR_PAD_LEFT,
        );
    }

    /**
     * The seconds remaining until the given timestamp, never negative.
     */
    private function secondsUntil(int $timestamp): int
    {
        return max(0, $timestamp - now()->getTimestamp());
    }

    private function store(): Repository
    {
        return $this->cache->store(config('otp.store'));
    }

    private function codeKey(string $msisdn, OtpPurpose $purpose): string
    {
        return "otp:{$purpose->keySegment()}:{$msisdn}";
    }

    private function plainKey(string $msisdn, OtpPurpose $purpose): string
    {
        return $this->codeKey($msisdn, $purpose).':plain';
    }

    private function cooldownKey(string $msisdn, OtpPurpose $purpose): string
    {
        return $this->codeKey($msisdn, $purpose).':cooldown';
    }

    private function length(): int
    {
        return (int) config('otp.length');
    }

    private function ttl(): int
    {
        return (int) config('otp.ttl');
    }

    private function maxAttempts(): int
    {
        return (int) config('otp.max_attempts');
    }

    private function resendCooldown(): int
    {
        return (int) config('otp.resend_cooldown');
    }
}
