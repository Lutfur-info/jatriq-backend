<?php

use App\enum\DocumentType;
use App\enum\Role;
use App\enum\VerificationStatus;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Storage::fake(config('verification.disk'));

    $this->driver = User::factory()->driver()->create();
    $this->passenger = User::factory()->passenger()->create();
});

/**
 * The identity set every rider submits: both NID pages and a profile photo.
 *
 * @return array<string, UploadedFile>
 */
function identityDocuments(): array
{
    return [
        'nid_front' => UploadedFile::fake()->image('nid-front.jpg'),
        'nid_back' => UploadedFile::fake()->image('nid-back.jpg'),
        'profile_photo' => UploadedFile::fake()->image('me.jpg'),
    ];
}

/**
 * The extras only a driver may add, neither of which the badge waits on.
 *
 * @return array<string, UploadedFile>
 */
function driverExtras(): array
{
    return [
        'driving_licence' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
        'vehicle_registration' => UploadedFile::fake()->create('registration.pdf', 200, 'application/pdf'),
    ];
}

it('asks a driver and a passenger for the very same documents', function () {
    expect(DocumentType::requiredNamesFor(Role::Driver))
        ->toBe(DocumentType::requiredNamesFor(Role::Passenger))
        ->toBe([
            DocumentType::NidFront->name,
            DocumentType::NidBack->name,
            DocumentType::ProfilePhoto->name,
        ]);
});

it('reports an unverified badge and every missing document before anything is uploaded', function (string $role) {
    $this->actingAs($this->{$role});

    $this->getJson(route('api.verification.show'))
        ->assertOk()
        ->assertJsonPath('data.role', $this->{$role}->role->name)
        ->assertJsonPath('data.status', VerificationStatus::Unverified->name)
        ->assertJsonPath('data.verified', false)
        ->assertJsonPath('data.required_documents', DocumentType::requiredNamesFor($this->{$role}->role))
        ->assertJsonPath('data.missing_documents', DocumentType::requiredNamesFor($this->{$role}->role))
        ->assertJsonCount(0, 'data.documents');
})->with(['driver', 'passenger']);

it('completes the set from one endpoint and moves the badge to pending', function (string $role) {
    $user = $this->{$role};

    $this->actingAs($user)
        ->post(route('api.verification.store'), identityDocuments(), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', VerificationStatus::Pending->name)
        ->assertJsonPath('data.missing_documents', [])
        ->assertJsonCount(3, 'data.documents');

    expect($user->refresh()->verification_status)->toBe(VerificationStatus::Pending)
        ->and($user->documents()->count())->toBe(3);

    $disk = Storage::disk(config('verification.disk'));

    $user->documents->each(fn (UserDocument $document) => $disk->assertExists($document->path));
})->with(['driver', 'passenger']);

it('stores a drivers licence and vehicle papers without the badge waiting on them', function () {
    $this->actingAs($this->driver)
        ->post(route('api.verification.store'), [...identityDocuments(), ...driverExtras()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', VerificationStatus::Pending->name)
        ->assertJsonPath('data.missing_documents', [])
        ->assertJsonCount(5, 'data.documents');

    expect($this->driver->documents()->count())->toBe(5);
});

it('badges a driver who never sends a licence, because it is not required', function () {
    $this->actingAs($this->driver)
        ->post(route('api.verification.store'), identityDocuments(), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.missing_documents', [])
        ->assertJsonPath('data.required_documents', DocumentType::requiredNamesFor(Role::Driver));

    expect(DocumentType::requiredNamesFor(Role::Driver))
        ->not->toContain(DocumentType::DrivingLicence->name)
        ->not->toContain(DocumentType::VehicleRegistration->name);
});

it('ignores a driving licence sent by a passenger, who has no use for one', function () {
    $this->actingAs($this->passenger)
        ->post(route('api.verification.store'), [
            'nid_front' => UploadedFile::fake()->image('nid-front.jpg'),
            ...driverExtras(),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    expect($this->passenger->documents()->count())->toBe(1)
        ->and($this->passenger->documents()->sole()->type)->toBe(DocumentType::NidFront);
});

it('accepts the documents one at a time and keeps the badge unverified until the set is complete', function () {
    $this->actingAs($this->passenger)
        ->post(route('api.verification.store'), [
            'nid_front' => UploadedFile::fake()->image('nid-front.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', VerificationStatus::Unverified->name)
        ->assertJsonCount(2, 'data.missing_documents');

    expect($this->passenger->documents()->count())->toBe(1);
});

it('replaces a re-uploaded document and discards the file it stood in for', function () {
    $this->actingAs($this->driver);

    $this->post(route('api.verification.store'), [
        'nid_front' => UploadedFile::fake()->image('first.jpg'),
    ], ['Accept' => 'application/json'])->assertOk();

    $original = $this->driver->documents()->sole();

    $this->post(route('api.verification.store'), [
        'nid_front' => UploadedFile::fake()->image('second.jpg'),
    ], ['Accept' => 'application/json'])->assertOk();

    $replacement = $this->driver->documents()->sole();
    $disk = Storage::disk(config('verification.disk'));

    expect($this->driver->documents()->count())->toBe(1)
        ->and($replacement->id)->toBe($original->id)
        ->and($replacement->path)->not->toBe($original->path)
        ->and($replacement->original_name)->toBe('second.jpg');

    $disk->assertMissing($original->path);
    $disk->assertExists($replacement->path);
});

it('sends a replaced document back into review and clears the rejection it carried', function () {
    $rejected = UserDocument::factory()
        ->for($this->passenger)
        ->ofType(DocumentType::NidFront)
        ->rejected()
        ->create();

    $this->actingAs($this->passenger)
        ->post(route('api.verification.store'), [
            'nid_front' => UploadedFile::fake()->image('clearer.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

    expect($rejected->refresh()->status->name)->toBe('Pending')
        ->and($rejected->rejection_reason)->toBeNull()
        ->and($rejected->reviewed_at)->toBeNull();
});

it('rejects a request that carries no file at all', function () {
    $this->actingAs($this->passenger)
        ->post(route('api.verification.store'), [], ['Accept' => 'application/json'])
        ->assertJsonValidationErrorFor('documents');

    expect($this->passenger->documents()->count())->toBe(0);
});

it('rejects a profile photo that is not an image', function () {
    $this->actingAs($this->passenger)
        ->post(route('api.verification.store'), [
            'profile_photo' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertJsonValidationErrorFor('profile_photo');
});

it('rejects a document larger than the configured ceiling', function () {
    $oversized = ((int) config('verification.max_size')) + 1;

    $this->actingAs($this->passenger)
        ->post(route('api.verification.store'), [
            'nid_front' => UploadedFile::fake()->create('huge.jpg', $oversized, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
        ->assertJsonValidationErrorFor('nid_front');
});

it('is closed to admins, who review rather than submit', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson(route('api.verification.show'))
        ->assertStatus(Response::HTTP_FORBIDDEN);

    $this->actingAs($admin)
        ->post(route('api.verification.store'), identityDocuments(), ['Accept' => 'application/json'])
        ->assertStatus(Response::HTTP_FORBIDDEN);

    expect($admin->documents()->count())->toBe(0);
});

it('requires authentication', function () {
    $this->getJson(route('api.verification.show'))->assertUnauthorized();
});

it('serves a submitted document to its owner but not to another user', function () {
    $this->actingAs($this->driver);

    $this->post(route('api.verification.store'), [
        'nid_front' => UploadedFile::fake()->image('nid-front.jpg'),
    ], ['Accept' => 'application/json'])->assertOk();

    $document = $this->driver->documents()->sole();

    $this->get(route('api.documents.show', $document))->assertOk();

    $this->actingAs(User::factory()->driver()->create())
        ->get(route('api.documents.show', $document))
        ->assertStatus(Response::HTTP_FORBIDDEN);
});

it('serves a submitted document to an administrator', function () {
    $document = UserDocument::factory()->for($this->driver)->create();

    Storage::disk(config('verification.disk'))->put($document->path, 'scan');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('api.documents.show', $document))
        ->assertOk();
});

it('exposes the badge on the user endpoint', function () {
    $verified = User::factory()->verified()->create();

    $this->actingAs($verified)
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertJsonPath('data.verification_status', VerificationStatus::Verified->name)
        ->assertJsonPath('data.verified', true);
});
