<?php

use App\Models\EmergencyContact;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->user = User::factory()->create(['msisdn' => '01712345678']);
});

/**
 * A valid contact payload, overridable field by field.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactPayload(array $overrides = []): array
{
    return [
        'name' => 'Amina Rahman',
        'relation' => 'Mother',
        'msisdn' => '01812345678',
        ...$overrides,
    ];
}

it('saves an emergency contact', function () {
    $this->actingAs($this->user)
        ->postJson(route('api.emergency-contacts.store'), contactPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Amina Rahman')
        ->assertJsonPath('data.relation', 'Mother')
        ->assertJsonPath('data.msisdn', '01812345678')
        ->assertJsonPath('data.dial_code', '00880')
        ->assertJsonPath('data.international_msisdn', '008801812345678');

    expect($this->user->emergencyContacts()->count())->toBe(1);
});

it('normalises a formatted number the way the auth endpoints do', function () {
    $this->actingAs($this->user)
        ->postJson(route('api.emergency-contacts.store'), contactPayload([
            'msisdn' => '018 12-345678',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.msisdn', '01812345678');
});

it('makes the first contact primary without being asked', function () {
    $this->actingAs($this->user)
        ->postJson(route('api.emergency-contacts.store'), contactPayload())
        ->assertCreated()
        ->assertJsonPath('data.is_primary', true);
});

it('moves the primary flag rather than holding two at once', function () {
    $first = EmergencyContact::factory()->for($this->user)->primary()->create();

    $this->actingAs($this->user)
        ->postJson(route('api.emergency-contacts.store'), contactPayload([
            'is_primary' => true,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.is_primary', true);

    expect($first->refresh()->is_primary)->toBeFalse()
        ->and($this->user->emergencyContacts()->where('is_primary', true)->count())->toBe(1);
});

it('lists the contacts primary first with the slots left', function () {
    EmergencyContact::factory()->for($this->user)->create(['name' => 'Second']);
    EmergencyContact::factory()->for($this->user)->primary()->create(['name' => 'First']);

    $this->actingAs($this->user)
        ->getJson(route('api.emergency-contacts.index'))
        ->assertOk()
        ->assertJsonPath('data.contacts.0.name', 'First')
        ->assertJsonPath('data.contacts.1.name', 'Second')
        ->assertJsonPath('data.remaining_slots', config('verification.emergency_contacts.max') - 2);
});

it('updates a contact in place', function () {
    $contact = EmergencyContact::factory()->for($this->user)->primary()->create();

    $this->actingAs($this->user)
        ->patchJson(route('api.emergency-contacts.update', $contact), [
            'relation' => 'Sister',
        ])
        ->assertOk()
        ->assertJsonPath('data.relation', 'Sister')
        ->assertJsonPath('data.name', $contact->name);
});

it('promotes a contact to primary on update', function () {
    $primary = EmergencyContact::factory()->for($this->user)->primary()->create();
    $other = EmergencyContact::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->patchJson(route('api.emergency-contacts.update', $other), ['is_primary' => true])
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);

    expect($primary->refresh()->is_primary)->toBeFalse();
});

it('promotes the next contact when the primary one is removed', function () {
    $primary = EmergencyContact::factory()->for($this->user)->primary()->create();
    $successor = EmergencyContact::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->deleteJson(route('api.emergency-contacts.destroy', $primary))
        ->assertOk();

    expect(EmergencyContact::find($primary->id))->toBeNull()
        ->and($successor->refresh()->is_primary)->toBeTrue();
});

it('refuses to save the user their own number', function () {
    $this->actingAs($this->user)
        ->postJson(route('api.emergency-contacts.store'), contactPayload([
            'msisdn' => $this->user->msisdn,
        ]))
        ->assertJsonValidationErrorFor('msisdn');
});

it('refuses to save the same number twice', function () {
    EmergencyContact::factory()->for($this->user)->create(['msisdn' => '01812345678']);

    $this->actingAs($this->user)
        ->postJson(route('api.emergency-contacts.store'), contactPayload())
        ->assertJsonValidationErrorFor('msisdn');
});

it('allows two users to list the same number', function () {
    EmergencyContact::factory()
        ->for(User::factory()->create())
        ->create(['msisdn' => '01812345678']);

    $this->actingAs($this->user)
        ->postJson(route('api.emergency-contacts.store'), contactPayload())
        ->assertCreated();
});

it('stops the list growing past the configured maximum', function () {
    $maximum = (int) config('verification.emergency_contacts.max');

    EmergencyContact::factory()->count($maximum)->for($this->user)->create();

    $this->actingAs($this->user)
        ->postJson(route('api.emergency-contacts.store'), contactPayload())
        ->assertJsonValidationErrorFor('msisdn');

    expect($this->user->emergencyContacts()->count())->toBe($maximum);
});

it('requires a name and a relation', function () {
    $this->actingAs($this->user)
        ->postJson(route('api.emergency-contacts.store'), ['msisdn' => '01812345678'])
        ->assertJsonValidationErrors(['name', 'relation']);
});

it('hides another users contact behind a 404', function () {
    $theirs = EmergencyContact::factory()
        ->for(User::factory()->create())
        ->create(['relation' => 'Mother']);

    $this->actingAs($this->user)
        ->patchJson(route('api.emergency-contacts.update', $theirs), ['relation' => 'Friend'])
        ->assertNotFound();

    $this->actingAs($this->user)
        ->deleteJson(route('api.emergency-contacts.destroy', $theirs))
        ->assertNotFound();

    expect($theirs->refresh()->relation)->toBe('Mother');
});

it('is open to a driver as well as a passenger', function (string $state) {
    $user = User::factory()->{$state}()->create(['msisdn' => '01799999999']);

    $this->actingAs($user)
        ->postJson(route('api.emergency-contacts.store'), contactPayload())
        ->assertCreated()
        ->assertJsonPath('data.is_primary', true);

    $this->actingAs($user)
        ->getJson(route('api.emergency-contacts.index'))
        ->assertOk()
        ->assertJsonPath('data.contacts.0.name', 'Amina Rahman');
})->with([
    'driver' => 'driver',
    'passenger' => 'passenger',
]);

it('keeps a drivers list separate from a passengers', function () {
    $driver = User::factory()->driver()->create();
    EmergencyContact::factory()->for($driver)->primary()->create(['name' => 'Drivers contact']);
    EmergencyContact::factory()->for($this->user)->primary()->create(['name' => 'Passengers contact']);

    $this->actingAs($driver)
        ->getJson(route('api.emergency-contacts.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.contacts')
        ->assertJsonPath('data.contacts.0.name', 'Drivers contact');
});

it('is closed to admins', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('api.emergency-contacts.index'))
        ->assertStatus(Response::HTTP_FORBIDDEN);
});

it('requires authentication', function () {
    $this->getJson(route('api.emergency-contacts.index'))->assertUnauthorized();
});
