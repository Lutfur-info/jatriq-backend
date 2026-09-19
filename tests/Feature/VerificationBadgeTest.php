<?php

use App\enum\DocumentStatus;
use App\enum\DocumentType;
use App\enum\Role;
use App\enum\VerificationStatus;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\VerificationService;

beforeEach(function () {
    $this->reviewer = User::factory()->admin()->create();
    $this->applicant = User::factory()->passenger()->create();
    $this->verification = app(VerificationService::class);
});

it('turns the badge green once every required document is approved', function () {
    $documents = submitRequiredDocuments($this->applicant);

    expect($this->applicant->refresh()->verification_status)->toBe(VerificationStatus::Pending);

    foreach ($documents as $document) {
        $this->verification->review($document, $this->reviewer, DocumentStatus::Approved);
    }

    $this->applicant->refresh();

    expect($this->applicant->verification_status)->toBe(VerificationStatus::Verified)
        ->and($this->applicant->isVerified())->toBeTrue()
        ->and($this->applicant->verified_at)->not->toBeNull();
});

it('leaves the badge pending while any required document is still unreviewed', function () {
    $documents = submitRequiredDocuments($this->applicant);

    $this->verification->review(
        $documents[DocumentType::NidFront->name],
        $this->reviewer,
        DocumentStatus::Approved,
    );

    expect($this->applicant->refresh()->verification_status)->toBe(VerificationStatus::Pending);
});

it('marks the badge rejected and records who decided and why', function () {
    $documents = submitRequiredDocuments($this->applicant);
    $document = $documents[DocumentType::NidBack->name];

    $this->verification->review(
        $document,
        $this->reviewer,
        DocumentStatus::Rejected,
        'The back page is cut off.',
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Rejected)
        ->and($document->rejection_reason)->toBe('The back page is cut off.')
        ->and($document->reviewed_by)->toBe($this->reviewer->id)
        ->and($document->reviewed_at)->not->toBeNull()
        ->and($this->applicant->refresh()->verification_status)->toBe(VerificationStatus::Rejected);
});

it('drops the badge off a verified user whose document is later rejected', function () {
    $documents = submitRequiredDocuments($this->applicant);

    foreach ($documents as $document) {
        $this->verification->review($document, $this->reviewer, DocumentStatus::Approved);
    }

    expect($this->applicant->refresh()->isVerified())->toBeTrue();

    $this->verification->review(
        $documents[DocumentType::ProfilePhoto->name],
        $this->reviewer,
        DocumentStatus::Rejected,
        'This is not a photo of a face.',
    );

    $this->applicant->refresh();

    expect($this->applicant->verification_status)->toBe(VerificationStatus::Rejected)
        ->and($this->applicant->verified_at)->toBeNull();
});

it('never badges an administrator, who submits nothing', function () {
    expect(DocumentType::requiredFor(Role::Admin))->toBe([])
        ->and($this->verification->refreshBadge($this->reviewer))
        ->toBe(VerificationStatus::Unverified);
});

it('ignores a document the role does not require when deriving the badge', function () {
    submitRequiredDocuments($this->applicant);

    // A licence is acceptable for a driver and irrelevant to a passenger; in
    // neither case does the badge wait on it.
    UserDocument::factory()
        ->for($this->applicant)
        ->ofType(DocumentType::DrivingLicence)
        ->create();

    expect($this->verification->refreshBadge($this->applicant))->toBe(VerificationStatus::Pending);
});
