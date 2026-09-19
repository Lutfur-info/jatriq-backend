<?php

namespace App\Repositories\Eloquent;

use App\enum\DocumentStatus;
use App\enum\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use App\Repositories\Contracts\UserDocumentRepository;
use Illuminate\Support\Collection;

/**
 * @phpstan-import-type DocumentAttributes from UserDocumentRepository
 */
class UserDocumentEloquentRepository implements UserDocumentRepository
{
    /**
     * {@inheritDoc}
     */
    public function forUser(User $user): Collection
    {
        return $user->documents()
            ->orderBy('id')
            ->get()
            ->keyBy(fn (UserDocument $document): string => $document->type->name);
    }

    /**
     * {@inheritDoc}
     */
    public function findForUser(User $user, DocumentType $type): ?UserDocument
    {
        return $user->documents()->where('type', $type->name)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function put(User $user, DocumentType $type, array $attributes): UserDocument
    {
        $document = $this->findForUser($user, $type) ?? $user->documents()->make();

        $document->fill([...$attributes, 'type' => $type]);

        // A replacement starts its review again, so an earlier decision on the
        // document it replaces can never carry over.
        $document->status = DocumentStatus::Pending;
        $document->rejection_reason = null;
        $document->reviewed_by = null;
        $document->reviewed_at = null;

        $document->user()->associate($user);
        $document->save();

        return $document;
    }

    /**
     * {@inheritDoc}
     */
    public function markReviewed(
        UserDocument $document,
        DocumentStatus $status,
        User $reviewer,
        ?string $rejectionReason,
    ): UserDocument {
        $document->forceFill([
            'status' => $status,
            'rejection_reason' => $status === DocumentStatus::Rejected ? $rejectionReason : null,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => $document->freshTimestamp(),
        ])->save();

        return $document;
    }
}
