<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();
    }

    /**
     * Throttle the endpoints that guess credentials or spend money on SMS.
     *
     * Each limit is segmented by msisdn as well as IP: an address alone
     * groups everyone behind one mobile carrier, while a msisdn alone lets
     * an attacker lock a specific account out.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-msisdn:'.$request->input('msisdn')),
        ]);

        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(10)->by('otp-ip:'.$request->ip()),
            Limit::perHour(5)->by('otp-msisdn:'.$request->input('msisdn')),
        ]);

        RateLimiter::for('tokens', fn (Request $request) => Limit::perMinute(10)
            ->by('tokens:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('otp-code', fn (Request $request) => [
            Limit::perMinute(20)->by('otp-code-ip:'.$request->ip()),
            Limit::perMinute(10)->by('otp-code-msisdn:'.$request->input('msisdn')),
        ]);

        // Uploads are large and land on disk, so they are capped per account.
        RateLimiter::for('document-upload', fn (Request $request) => Limit::perHour(30)
            ->by('documents:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // The only unauthenticated read in the API, so it is capped by
        // address - there is no account to key it to.
        RateLimiter::for('rides-browse', fn (Request $request) => Limit::perMinute(60)
            ->by('rides-browse:'.$request->ip()));

        /*
         * Publishing a ride costs nothing and guesses nothing, but a loop
         * posting them would fill every passenger's search results - so it
         * is capped per account rather than per address.
         */
        RateLimiter::for('ride-create', fn (Request $request) => Limit::perHour(60)
            ->by('rides:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinute(20)->by('verify-ip:'.$request->ip()),
            Limit::perMinute(10)->by('verify-msisdn:'.$request->input('msisdn')),
        ]);

        /*
         * Password recovery, held to its own budget rather than sharing the
         * verification one: a reset request must not use up the codes a user
         * needs to activate their account, or the other way round.
         */
        RateLimiter::for('password-forgot', fn (Request $request) => [
            Limit::perMinute(10)->by('forgot-ip:'.$request->ip()),
            Limit::perHour(5)->by('forgot-msisdn:'.$request->input('msisdn')),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(20)->by('reset-ip:'.$request->ip()),
            Limit::perMinute(10)->by('reset-msisdn:'.$request->input('msisdn')),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
