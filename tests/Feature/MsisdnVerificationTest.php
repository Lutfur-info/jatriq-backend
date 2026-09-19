<?php

use App\Models\User;
use App\Services\OtpService;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->user = User::factory()->unverifiedMsisdn()->create(['msisdn' => '01712345678']);
    $this->otp = app(OtpService::class);
});

it('verifies the msisdn, activates the account and returns a token', function () {
    $code = $this->otp->send($this->user);

    $response = $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.msisdn_verified', true)
        ->assertJsonPath('data.user.is_active', true)
        ->assertJsonStructure(['data' => ['token']]);

    expect($this->user->refresh()->hasVerifiedMsisdn())->toBeTrue()
        ->and($this->user->is_active)->toBeTrue();
});

it('rejects an incorrect code', function () {
    $code = $this->otp->send($this->user);

    $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $this->user->msisdn,
        'code' => str_pad((string) ((int) $code + 1), 6, '0', STR_PAD_LEFT),
    ])->assertJsonValidationErrorFor('code');

    expect($this->user->refresh()->hasVerifiedMsisdn())->toBeFalse();
});

it('cannot reuse a code once it has been accepted', function () {
    $code = $this->otp->send($this->user);

    $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
    ])->assertOk();

    $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
    ])->assertJsonValidationErrorFor('msisdn');
});

it('discards the code after too many incorrect attempts', function () {
    $this->otp->send($this->user);

    foreach (range(1, config('otp.max_attempts')) as $attempt) {
        $this->postJson(route('api.msisdn.verify'), [
            'msisdn' => $this->user->msisdn,
            'code' => '000000',
        ]);
    }

    $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $this->user->msisdn,
        'code' => '000000',
    ])->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
});

it('rejects a code once it has expired', function () {
    $this->otp->send($this->user);

    $this->travel(config('otp.ttl') + 1)->seconds();

    $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $this->user->msisdn,
        'code' => '000000',
    ])->assertJsonValidationErrorFor('code');
});

it('holds a resend back until the cooldown has passed', function () {
    $this->otp->send($this->user);

    $this->postJson(route('api.msisdn.resend'), ['msisdn' => $this->user->msisdn])
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
        ->assertJsonPath('data.resend_available_in', fn (int $seconds) => $seconds > 0);

    $this->travel(config('otp.resend_cooldown') + 1)->seconds();

    $this->postJson(route('api.msisdn.resend'), ['msisdn' => $this->user->msisdn])
        ->assertOk();
});

it('does not send a code to a number that is already verified', function () {
    $verified = User::factory()->create(['msisdn' => '01799999999']);

    $this->postJson(route('api.msisdn.resend'), ['msisdn' => $verified->msisdn])
        ->assertJsonValidationErrorFor('msisdn');
});

it('does not reveal codes for unknown numbers', function () {
    $this->postJson(route('api.msisdn.resend'), ['msisdn' => '01700000000'])
        ->assertJsonValidationErrorFor('msisdn');
});
