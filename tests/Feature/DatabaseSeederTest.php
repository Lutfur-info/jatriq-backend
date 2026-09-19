<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('signs every seeded account in with the seeded password', function (string $msisdn) {
    $this->seed(DatabaseSeeder::class);

    $this->postJson(route('api.login'), [
        'msisdn' => $msisdn,
        'password' => 'Win4Win$',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['token']]);
})->with(['01700000000', '01700000001', '01700000002']);

it('no longer accepts the factory default password for a seeded account', function () {
    $this->seed(DatabaseSeeder::class);

    $this->postJson(route('api.login'), [
        'msisdn' => '01700000000',
        'password' => 'password',
    ])->assertJsonValidationErrorFor('msisdn');

    expect(User::where('msisdn', '01700000000')->firstOrFail()->tokens()->count())->toBe(0);
});
