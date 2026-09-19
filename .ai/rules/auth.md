---
paths:
  - 'app/Http/Controllers/Api/Auth/**'
---

# Auth

## API auth runs on msisdn + OTP, not email
Login and registration identify users by `msisdn` (digits only; requests use the `NormalizesMsisdn` trait to strip formatting). Email is optional and unused for auth.

Verification codes are NOT stored in `password_reset_tokens`. Laravel's `DatabaseTokenRepository` hardcodes the `email` column, so the password broker cannot be used with this schema. Codes live in the cache via `App\Services\OtpService` (hashed, TTL + attempt counter + resend cooldown from `config/otp.php`).

Registration leaves `msisdn_verified_at` null and `is_active` false; verifying the code sets both and issues the first Sanctum token. Login refuses an unverified account with 403 and re-sends a code.

## One code, one purpose
Every code is issued for an `App\enum\OtpPurpose` and only ever read back under that purpose, because the cache key carries it (`otp:{purpose}:{msisdn}`). A new flow that sends a code adds a case — never reuses an existing one — or a code sent for one thing becomes spendable on another: a password reset code that also satisfied `/msisdn/verify` would hand an account activation to anyone who asked for a reset.

`PasswordReset` is that second purpose today (`PasswordResetController`, `/api/password/{forgot,reset}`). It resets by OTP, not by Laravel's password broker: `password_reset_tokens` is keyed by msisdn and the broker hardcodes `email`.

## An unverified number gets one answer, wherever it is asked
Sign in and forgotten password both refuse it through `RefusesUnverifiedMsisdn` — 403, a verification code sent if the cooldown allows, and `data.msisdn_verified: false` for the client to branch on. Do not invent a second shape for it: the mobile client keys off that flag, not the message. An unverified account needs no reset anyway, since verifying the code signs the user in without a password.

Every one of these routes carries a named rate limiter defined in `AppServiceProvider::configureRateLimiters()` — SMS costs money.
