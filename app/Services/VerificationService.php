<?php

namespace App\Services;

use App\enum\DocumentStatus;
use App\enum\DocumentType;
use App\enum\VerificationStatus;
use App\Models\User;
use App\Models\UserDocument;
use App\Repositories\Contracts\UserDocumentRepository;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Accepts identity documents and keeps the verification badge in step.
 *
 * The badge is never written directly. Every path that changes a document -
 * an upload, a replacement, an approval, a rejection - ends by recomputing it
 * from the documents the user's role requires, so the two can never drift.
 */
class VerificationService
{
    public function __construct(
        private UserDocumentRepository $documents,
        private UserRepository $users,
        private FilesystemFactory $filesystem,
    ) {}

    /**
     * Store a batch of uploads for the user and recompute their badge.
     *
     * @param  array<string, UploadedFile>  $files  keyed by DocumentType name
     * @return Collection<string, UserDocument>
     */
    public function storeMany(User $user, array $files): Collection
    {
        DB::transaction(function () use ($user, $files): void {
            foreach ($files as $name => $file) {
                $this->store($user, DocumentType::fromName($name), $file);
            }
        });

        $this->refreshBadge($user);

        return $this->documents->forUser($user);
    }

    /**
     * Store one upload, discarding the file it replaces.
     */
    public function store(User $user, DocumentType $type, UploadedFile $file): UserDocument
    {
        $replaced = $this->documents->findForUser($user, $type);
        $replacedDisk = $replaced?->disk;
        $replacedPath = $replaced?->path;

        $document = $this->documents->put($user, $type, [
            'disk' => $this->disk(),
            'path' => $this->write($user, $type, $file),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
        ]);

        if ($replacedDisk !== null && $replacedPath !== null && $replacedPath !== $document->path) {
            $this->filesystem->disk($replacedDisk)->delete($replacedPath);
        }

        return $document;
    }

    /**
     * Record a reviewer's decision and recompute the applicant's badge.
     */
    public function review(
        UserDocument $document,
        User $reviewer,
        DocumentStatus $status,
        ?string $rejectionReason = null,
    ): UserDocument {
        $document = $this->documents->markReviewed($document, $status, $reviewer, $rejectionReason);

        $this->refreshBadge($document->user);

        return $document;
    }

    /**
     * Derive the badge from the documents the user's role requires.
     *
     * A role with no requirements - an admin - is never badged.
     */
    public function refreshBadge(User $user): VerificationStatus
    {
        $required = DocumentType::requiredFor($user->role);
        $submitted = $this->documents->forUser($user);

        $status = $this->deriveStatus($required, $submitted);

        $this->users->updateVerification(
            $user,
            $status,
            $status === VerificationStatus::Verified ? now() : null,
        );

        return $status;
    }

    /**
     * The documents the user's role requires but has not submitted yet.
     *
     * @return array<int, DocumentType>
     */
    public function missingFor(User $user): array
    {
        $submitted = $this->documents->forUser($user);

        return array_values(array_filter(
            DocumentType::requiredFor($user->role),
            fn (DocumentType $type): bool => ! $submitted->has($type->name),
        ));
    }

    /**
     * Work out which badge the submitted documents add up to.
     *
     * @param  array<int, DocumentType>  $required
     * @param  Collection<string, UserDocument>  $submitted
     */
    private function deriveStatus(array $required, Collection $submitted): VerificationStatus
    {
        if ($required === []) {
            return VerificationStatus::Unverified;
        }

        $documents = array_map(
            fn (DocumentType $type): ?UserDocument => $submitted->get($type->name),
            $required,
        );

        return match (true) {
            // A single rejection is what the applicant needs to act on, so it
            // outranks the documents still waiting behind it.
            $this->anyRejected($documents) => VerificationStatus::Rejected,
            in_array(null, $documents, true) => VerificationStatus::Unverified,
            $this->allApproved($documents) => VerificationStatus::Verified,
            default => VerificationStatus::Pending,
        };
    }

    /**
     * @param  array<int, UserDocument|null>  $documents
     */
    private function anyRejected(array $documents): bool
    {
        return collect($documents)->contains(fn (?UserDocument $document): bool => (bool) $document?->isRejected());
    }

    /**
     * @param  array<int, UserDocument|null>  $documents
     */
    private function allApproved(array $documents): bool
    {
        return collect($documents)->every(fn (?UserDocument $document): bool => (bool) $document?->isApproved());
    }

    /**
     * Write the upload to the private disk and return its path.
     */
    private function write(User $user, DocumentType $type, UploadedFile $file): string
    {
        $directory = trim((string) config('verification.directory'), '/')."/{$user->getKey()}";
        $extension = $file->extension() ?: $file->getClientOriginalExtension();
        $filename = $type->field().'-'.Str::ulid().($extension === '' ? '' : ".{$extension}");

        $path = $file->storeAs($directory, $filename, $this->disk());

        if (! is_string($path)) {
            throw new RuntimeException("Unable to store the {$type->label()} upload.");
        }

        return $path;
    }

    private function disk(): string
    {
        return (string) config('verification.disk');
    }
}
