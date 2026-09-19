<?php

use App\enum\OtpPurpose;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->user = User::factory()->create(['msisdn' => '01712345678']);
    $this->otp = app(OtpService::class);
});

it('sends a reset code to a known number', function () {
    $response = $this->postJson(route('api.password.forgot'), ['msisdn' => $this->user->msisdn]);

    $response->assertOk()
        ->assertJsonPath('data.msisdn', $this->user->msisdn)
        ->assertJsonPath('data.resend_available_in', fn (int $seconds) => $seconds > 0);

    expect($this->otp->issuedCode($this->user->msisdn, OtpPurpose::PasswordReset))->not->toBeNull();
});

it('resets the password, revokes old tokens and returns a new one', function () {
    $staleToken = $this->user->createToken('old device')->plainTextToken;
    $code = $this->otp->send($this->user, OtpPurpose::PasswordReset);

    $response = $this->postJson(route('api.password.reset'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.id', $this->user->id)
        ->assertJsonStructure(['data' => ['token']]);

    expect(Hash::check('new-password', $this->user->refresh()->password))->toBeTrue()
        ->and($this->user->tokens()->count())->toBe(1);

    $this->withHeader('Authorization', "Bearer {$staleToken}")
        ->getJson(route('api.user'))
        ->assertUnauthorized();
});

it('lets the user log in with the new password and not the old one', function () {
    $code = $this->otp->send($this->user, OtpPurpose::PasswordReset);

    $this->postJson(route('api.password.reset'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();

    $this->postJson(route('api.login'), [
        'msisdn' => $this->user->msisdn,
        'password' => 'password',
    ])->assertJsonValidationErrorFor('msisdn');

    $this->postJson(route('api.login'), [
        'msisdn' => $this->user->msisdn,
        'password' => 'new-password',
    ])->assertOk();
});

it('rejects an incorrect code and leaves the password alone', function () {
    $code = $this->otp->send($this->user, OtpPurpose::PasswordReset);

    $this->postJson(route('api.password.reset'), [
        'msisdn' => $this->user->msisdn,
        'code' => str_pad((string) ((int) $code + 1), 6, '0', STR_PAD_LEFT),
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertJsonValidationErrorFor('code');

    expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
});

it('rejects a code once it has expired', function () {
    $code = $this->otp->send($this->user, OtpPurpose::PasswordReset);

    $this->travel(config('otp.ttl') + 1)->seconds();

    $this->postJson(route('api.password.reset'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertJsonValidationErrorFor('code');
});

it('discards the code after too many incorrect attempts', function () {
    $this->otp->send($this->user, OtpPurpose::PasswordReset);

    foreach (range(1, config('otp.max_attempts')) as $attempt) {
        $this->postJson(route('api.password.reset'), [
            'msisdn' => $this->user->msisdn,
            'code' => '000000',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
    }

    $this->postJson(route('api.password.reset'), [
        'msisdn' => $this->user->msisdn,
        'code' => '000000',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
});

it('cannot reuse a code once the password has been reset', function () {
    $code = $this->otp->send($this->user, OtpPurpose::PasswordReset);

    $payload = [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ];

    $this->postJson(route('api.password.reset'), $payload)->assertOk();

    $this->postJson(route('api.password.reset'), [
        ...$payload,
        'password' => 'newer-password',
        'password_confirmation' => 'newer-password',
    ])->assertJsonValidationErrorFor('code');
});

it('requires the new password to be confirmed', function () {
    $code = $this->otp->send($this->user, OtpPurpose::PasswordReset);

    $this->postJson(route('api.password.reset'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
        'password' => 'new-password',
        'password_confirmation' => 'different-password',
    ])->assertJsonValidationErrorFor('password');

    expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
});

it('holds a resend back until the cooldown has passed', function () {
    $this->postJson(route('api.password.forgot'), ['msisdn' => $this->user->msisdn])->assertOk();

    $this->postJson(route('api.password.forgot'), ['msisdn' => $this->user->msisdn])
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
        ->assertJsonPath('data.resend_available_in', fn (int $seconds) => $seconds > 0);

    $this->travel(config('otp.resend_cooldown') + 1)->seconds();

    $this->postJson(route('api.password.forgot'), ['msisdn' => $this->user->msisdn])->assertOk();
});

it('does not reveal whether an unknown number has an account', function () {
    $this->postJson(route('api.password.forgot'), ['msisdn' => '01700000000'])
        ->assertJsonValidationErrorFor('msisdn');

    $this->postJson(route('api.password.reset'), [
        'msisdn' => '01700000000',
        'code' => '000000',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertJsonValidationErrorFor('msisdn');
});

it('points an unverified number at verification instead of a reset', function () {
    $unverified = User::factory()->unverifiedMsisdn()->create(['msisdn' => '01799999999']);

    $this->postJson(route('api.password.forgot'), ['msisdn' => $unverified->msisdn])
        ->assertStatus(Response::HTTP_FORBIDDEN)
        ->assertJsonPath('data.msisdn_verified', false)
        ->assertJsonPath('data.resend_available_in', fn (int $seconds) => $seconds > 0);

    // Answered like a refused login: a verification code goes out, and no
    // reset code is issued for a number nobody has proved they hold.
    expect($this->otp->issuedCode($unverified->msisdn))->not->toBeNull()
        ->and($this->otp->issuedCode($unverified->msisdn, OtpPurpose::PasswordReset))->toBeNull();
});

it('refuses to reset the password of a deactivated account', function () {
    $inactive = User::factory()->inactive()->create(['msisdn' => '01788888888']);

    $this->postJson(route('api.password.forgot'), ['msisdn' => $inactive->msisdn])
        ->assertStatus(Response::HTTP_FORBIDDEN);
});

it('does not let a reset code verify a msisdn', function () {
    $unverified = User::factory()->unverifiedMsisdn()->create(['msisdn' => '01777777777']);
    $code = $this->otp->send($unverified, OtpPurpose::PasswordReset);

    $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $unverified->msisdn,
        'code' => $code,
    ])->assertJsonValidationErrorFor('code');

    expect($unverified->refresh()->hasVerifiedMsisdn())->toBeFalse();
});

it('does not let a verification code reset a password', function () {
    $code = $this->otp->send($this->user);

    $this->postJson(route('api.password.reset'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertJsonValidationErrorFor('code');

    expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
});

it('reads back the held reset code while codes are exposed', function () {
    $code = $this->otp->send($this->user, OtpPurpose::PasswordReset);

    $this->postJson(route('api.msisdn.code'), [
        'msisdn' => $this->user->msisdn,
        'purpose' => OtpPurpose::PasswordReset->name,
    ])->assertOk()
        ->assertJsonPath('data.code', $code)
        ->assertJsonPath('data.purpose', OtpPurpose::PasswordReset->name);
});
