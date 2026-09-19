<?php

use App\Models\User;
use App\Services\OtpService;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->user = User::factory()->unverifiedMsisdn()->create(['msisdn' => '01712345678']);
    $this->otp = app(OtpService::class);
});

it('returns the code currently held for the msisdn', function () {
    $code = $this->otp->send($this->user);

    $this->postJson(route('api.msisdn.code'), ['msisdn' => $this->user->msisdn])
        ->assertOk()
        ->assertJsonPath('data.msisdn', $this->user->msisdn)
        ->assertJsonPath('data.code', $code)
        ->assertJsonPath('data.expires_in', config('otp.ttl'));
});

it('accepts a formatted msisdn', function () {
    $code = $this->otp->send($this->user);

    $this->postJson(route('api.msisdn.code'), ['msisdn' => '017 12-345678'])
        ->assertOk()
        ->assertJsonPath('data.code', $code);
});

it('leaves the code usable for verification', function () {
    $code = $this->otp->send($this->user);

    $this->postJson(route('api.msisdn.code'), ['msisdn' => $this->user->msisdn])->assertOk();

    $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
    ])->assertOk();
});

it('does not start a resend cooldown', function () {
    $this->otp->send($this->user);

    $this->travel(config('otp.resend_cooldown') + 1)->seconds();

    $this->postJson(route('api.msisdn.code'), ['msisdn' => $this->user->msisdn])->assertOk();

    expect($this->otp->canResend($this->user->msisdn))->toBeTrue();
});

it('fails when no code has been issued', function () {
    $this->postJson(route('api.msisdn.code'), ['msisdn' => $this->user->msisdn])
        ->assertJsonValidationErrorFor('msisdn');
});

it('fails once the code has been verified', function () {
    $code = $this->otp->send($this->user);

    $this->postJson(route('api.msisdn.verify'), [
        'msisdn' => $this->user->msisdn,
        'code' => $code,
    ])->assertOk();

    $this->postJson(route('api.msisdn.code'), ['msisdn' => $this->user->msisdn])
        ->assertJsonValidationErrorFor('msisdn');
});

it('fails for a number with no account', function () {
    $this->postJson(route('api.msisdn.code'), ['msisdn' => '01799999999'])
        ->assertJsonValidationErrorFor('msisdn');
});

it('is hidden while code exposure is disabled', function () {
    config()->set('otp.expose_codes', false);

    $this->otp->send($this->user);

    $this->postJson(route('api.msisdn.code'), ['msisdn' => $this->user->msisdn])
        ->assertStatus(Response::HTTP_NOT_FOUND);
});

it('does not store a plain code while exposure is disabled', function () {
    config()->set('otp.expose_codes', false);

    $this->otp->send($this->user);

    config()->set('otp.expose_codes', true);

    $this->postJson(route('api.msisdn.code'), ['msisdn' => $this->user->msisdn])
        ->assertJsonValidationErrorFor('msisdn');
});
