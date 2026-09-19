<?php

use App\enum\Role;
use App\Models\User;
use App\Services\OtpService;

it('registers a passenger and leaves the account awaiting verification', function () {
    $response = $this->postJson(route('api.register'), [
        'first_name' => 'Lutfur',
        'last_name' => 'Rahman',
        'msisdn' => '017 12-345678',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.msisdn', '01712345678')
        ->assertJsonPath('data.user.role', Role::Passenger->name)
        ->assertJsonPath('data.user.msisdn_verified', false)
        ->assertJsonPath('data.user.is_active', false)
        ->assertJsonMissingPath('data.token');

    $user = User::firstWhere('msisdn', '01712345678');

    expect($user)->not->toBeNull()
        ->and($user->msisdn_verified_at)->toBeNull()
        ->and($user->is_active)->toBeFalse()
        ->and($user->dial_code)->toBe('00880');
});

it('registers a driver when the driver role is requested', function () {
    $this->postJson(route('api.register'), [
        'first_name' => 'Lutfur',
        'last_name' => 'Rahman',
        'msisdn' => '01712345678',
        'role' => Role::Driver->name,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated()->assertJsonPath('data.user.role', Role::Driver->name);
});

it('refuses to register an administrator', function () {
    $this->postJson(route('api.register'), [
        'first_name' => 'Lutfur',
        'last_name' => 'Rahman',
        'msisdn' => '01712345678',
        'role' => Role::Admin->name,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertJsonValidationErrorFor('role');

    expect(User::count())->toBe(0);
});

it('rejects a msisdn that is already registered', function () {
    User::factory()->create(['msisdn' => '01712345678']);

    $this->postJson(route('api.register'), [
        'first_name' => 'Lutfur',
        'last_name' => 'Rahman',
        'msisdn' => '01712345678',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertJsonValidationErrorFor('msisdn');
});

it('sends a verification code that activates the new account', function () {
    $this->postJson(route('api.register'), [
        'first_name' => 'Lutfur',
        'last_name' => 'Rahman',
        'msisdn' => '01712345678',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated();

    $user = User::firstWhere('msisdn', '01712345678');

    // The stored code is hashed, so re-issue one to learn its plain value.
    $code = app(OtpService::class)->send($user);

    $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $user->msisdn,
        'code' => $code,
    ])->assertOk();

    expect($user->refresh()->hasVerifiedMsisdn())->toBeTrue();
});
