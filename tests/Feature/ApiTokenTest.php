<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('issues a new token to the authenticated user', function () {
    $this->actingAs($this->user);

    $response = $this->postJson(route('api.tokens.store'), ['device_name' => 'Pixel 8'])
        ->assertCreated()
        ->assertJsonPath('data.device_name', 'Pixel 8')
        ->assertJsonStructure(['data' => ['id', 'device_name', 'token']]);

    $token = $response->json('data.token');

    expect(PersonalAccessToken::findToken($token)?->tokenable_id)->toBe($this->user->id);
});

it('authenticates later requests with the issued token', function () {
    $this->actingAs($this->user);

    $token = $this->postJson(route('api.tokens.store'))->json('data.token');

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);
});

it('leaves the token that authorised the request working', function () {
    $existing = $this->user->createToken('existing')->plainTextToken;

    $this->withToken($existing)->postJson(route('api.tokens.store'))->assertCreated();

    $this->withToken($existing)->getJson(route('api.user'))->assertOk();

    expect($this->user->tokens()->count())->toBe(2);
});

it('falls back to the user agent when no device name is given', function () {
    $this->actingAs($this->user);

    $this->postJson(route('api.tokens.store'), [], ['User-Agent' => 'JatriqApp/1.0'])
        ->assertCreated()
        ->assertJsonPath('data.device_name', 'JatriqApp/1.0');
});

it('rejects a device name that is too long', function () {
    $this->actingAs($this->user);

    $this->postJson(route('api.tokens.store'), ['device_name' => str_repeat('a', 256)])
        ->assertJsonValidationErrorFor('device_name');

    expect($this->user->tokens()->count())->toBe(0);
});

it('refuses to issue a token for a deactivated account', function () {
    $this->actingAs(User::factory()->inactive()->create());

    $this->postJson(route('api.tokens.store'))
        ->assertStatus(Response::HTTP_FORBIDDEN);
});

it('requires authentication', function () {
    $this->postJson(route('api.tokens.store'))
        ->assertUnauthorized();
});
