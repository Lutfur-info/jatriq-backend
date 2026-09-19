<?php

use App\enum\DocumentStatus;
use App\enum\DocumentType;
use App\enum\VerificationStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Models\UserDocument;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Storage::fake(config('verification.disk'));

    $this->admin = User::factory()->admin()->create();
    $this->applicant = User::factory()->passenger()->create();
});

/**
 * The documents relation manager, mounted on one applicant's review page.
 */
function documentsManager(User $admin, User $applicant): Testable
{
    test()->actingAs($admin);

    return Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $applicant,
        'pageClass' => ViewUser::class,
    ]);
}

/**
 * One document's row action, addressed the way a table action must be.
 */
function documentAction(string $name, UserDocument $document): TestAction
{
    return TestAction::make($name)->table($document);
}

it('sends a guest to the panel login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets an active admin in', function () {
    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertOk();
});

it('keeps riders out of the panel', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/admin')
        ->assertStatus(Response::HTTP_FORBIDDEN);
})->with(['driver', 'passenger']);

it('admits admins only, and only while they are active', function () {
    $panel = Filament::getPanel('admin');

    expect($this->admin->canAccessPanel($panel))->toBeTrue()
        ->and(User::factory()->driver()->create()->canAccessPanel($panel))->toBeFalse()
        ->and(User::factory()->passenger()->create()->canAccessPanel($panel))->toBeFalse()
        ->and(User::factory()->admin()->create(['is_active' => false])->canAccessPanel($panel))->toBeFalse();
});

it('refuses a rider at the login form rather than issuing a session', function () {
    $rider = User::factory()->driver()->create([
        'email' => 'rider@jatriq.test',
        'password' => Hash::make('secret-password'),
    ]);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $rider->email,
            'password' => 'secret-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth()->check())->toBeFalse();
});

it('signs an admin in through the panel login form', function () {
    $this->admin->update(['email' => 'reviewer@jatriq.test', 'password' => Hash::make('secret-password')]);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'reviewer@jatriq.test',
            'password' => 'secret-password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->id())->toBe($this->admin->id);
});

it('lists riders awaiting review and leaves admins out of the queue', function () {
    submitRequiredDocuments($this->applicant);
    $driver = User::factory()->driver()->create();
    submitRequiredDocuments($driver);

    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class, ['activeTab' => 'all'])
        ->assertCanSeeTableRecords([$this->applicant, $driver])
        ->assertCanNotSeeTableRecords([$this->admin]);
});

it('separates the queue by badge', function () {
    submitRequiredDocuments($this->applicant);
    $untouched = User::factory()->passenger()->create();

    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class, ['activeTab' => 'pending'])
        ->assertCanSeeTableRecords([$this->applicant])
        ->assertCanNotSeeTableRecords([$untouched]);
});

it('shows the applicants profile and badge on the review page', function () {
    submitRequiredDocuments($this->applicant);

    $this->actingAs($this->admin)
        ->get(UserResource::getUrl('view', ['record' => $this->applicant]))
        ->assertOk()
        ->assertSee($this->applicant->full_name)
        ->assertSee($this->applicant->msisdn);
});

it('approves a document through the service and moves the badge with it', function () {
    $documents = submitRequiredDocuments($this->applicant);
    $document = $documents[DocumentType::NidFront->name];

    documentsManager($this->admin, $this->applicant)
        ->callAction(documentAction('approve', $document))
        ->assertHasNoActionErrors();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Approved)
        ->and($document->reviewed_by)->toBe($this->admin->id)
        ->and($document->reviewed_at)->not->toBeNull()
        ->and($this->applicant->refresh()->verification_status)->toBe(VerificationStatus::Pending);
});

it('verifies the applicant once every required document is approved', function () {
    $documents = submitRequiredDocuments($this->applicant);

    $manager = documentsManager($this->admin, $this->applicant);

    foreach ($documents as $document) {
        $manager->callAction(documentAction('approve', $document));
    }

    $this->applicant->refresh();

    expect($this->applicant->verification_status)->toBe(VerificationStatus::Verified)
        ->and($this->applicant->verified_at)->not->toBeNull();
});

it('rejects a document with the reason the applicant will read', function () {
    $documents = submitRequiredDocuments($this->applicant);
    $document = $documents[DocumentType::NidBack->name];

    documentsManager($this->admin, $this->applicant)
        ->callAction(documentAction('reject', $document), [
            'rejection_reason' => 'The back page is cut off.',
        ])
        ->assertHasNoActionErrors();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Rejected)
        ->and($document->rejection_reason)->toBe('The back page is cut off.')
        ->and($this->applicant->refresh()->verification_status)->toBe(VerificationStatus::Rejected);
});

it('demands a reason before it will reject', function () {
    $documents = submitRequiredDocuments($this->applicant);
    $document = $documents[DocumentType::NidFront->name];

    documentsManager($this->admin, $this->applicant)
        ->callAction(documentAction('reject', $document), [
            'rejection_reason' => null,
        ])
        ->assertHasActionErrors(['rejection_reason' => ['required']]);

    expect($document->refresh()->status)->toBe(DocumentStatus::Pending);
});

it('mounts the documents relation manager on the review page', function () {
    submitRequiredDocuments($this->applicant);

    // The table itself is lazy-loaded, so it is absent from the first render
    // by design; what must hold is that the manager is registered and visible
    // for the record, which is what puts the review actions on the page.
    expect(UserResource::getRelations())->toBe([DocumentsRelationManager::class])
        ->and(DocumentsRelationManager::canViewForRecord($this->applicant, ViewUser::class))->toBeTrue();
});

it('never creates or edits an applicant from the panel', function () {
    expect(UserResource::canCreate())->toBeFalse()
        ->and(array_keys(UserResource::getPages()))->toBe(['index', 'view']);
});

it('streams a document to a reviewer on a panel session, with no api token', function () {
    $document = UserDocument::factory()->for($this->applicant)->create();

    Storage::disk(config('verification.disk'))->put($document->path, 'scan');

    $this->actingAs($this->admin)
        ->get(route('api.documents.show', ['document' => $document]))
        ->assertOk();
});
