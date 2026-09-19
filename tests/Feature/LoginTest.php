<?php

use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

it('issues a token for correct credentials', function () {
    $user = User::factory()->create(['msisdn' => '01712345678']);

    $response = $this->postJson(route('api.login'), [
        'msisdn' => '01712345678',
        'password' => 'password',
        'device_name' => 'iPhone 17',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['token']]);

    expect($user->tokens()->where('name', 'iPhone 17')->exists())->toBeTrue();
});

it('accepts a formatted msisdn', function () {
    User::factory()->create(['msisdn' => '01712345678']);

    $this->postJson(route('api.login'), [
        'msisdn' => '017 12-345678',
        'password' => 'password',
    ])->assertOk();
});

it('rejects a wrong password', function () {
    User::factory()->create(['msisdn' => '01712345678']);

    $this->postJson(route('api.login'), [
        'msisdn' => '01712345678',
        'password' => 'wrong-password',
    ])->assertJsonValidationErrorFor('msisdn');
});

it('rejects an unknown msisdn', function () {
    $this->postJson(route('api.login'), [
        'msisdn' => '01700000000',
        'password' => 'password',
    ])->assertJsonValidationErrorFor('msisdn');
});

it('refuses to log in an unverified msisdn and sends a new code', function () {
    $user = User::factory()->unverifiedMsisdn()->create(['msisdn' => '01712345678']);

    $this->postJson(route('api.login'), [
        'msisdn' => '01712345678',
        'password' => 'password',
    ])->assertStatus(Response::HTTP_FORBIDDEN)
        ->assertJsonPath('data.msisdn_verified', false);

    expect($user->tokens()->count())->toBe(0);
});

it('refuses to log in a deactivated account', function () {
    User::factory()->inactive()->create(['msisdn' => '01712345678']);

    $this->postJson(route('api.login'), [
        'msisdn' => '01712345678',
        'password' => 'password',
    ])->assertStatus(Response::HTTP_FORBIDDEN);
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('rejects an unauthenticated request for the current user', function () {
    $this->getJson(route('api.user'))->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson(route('api.logout'))->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
