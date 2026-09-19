<?php

namespace App\Repositories\Contracts;

use App\enum\DocumentStatus;
use App\enum\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Support\Collection;

/**
 * Persistence for the identity documents behind the verification badge.
 *
 * @phpstan-type DocumentAttributes array{
 *     disk: string,
 *     path: string,
 *     original_name: string,
 *     mime_type: string,
 *     size: int,
 * }
 */
interface UserDocumentRepository
{
    /**
     * Every document the user has submitted, keyed by document type name.
     *
     * @return Collection<string, UserDocument>
     */
    public function forUser(User $user): Collection;

    /**
     * The user's current document of the given type, if they have one.
     */
    public function findForUser(User $user, DocumentType $type): ?UserDocument;

    /**
     * Record an upload, replacing whatever the user held for that type.
     *
     * The returned document is always back in review, whatever state the
     * replaced one was in.
     *
     * @param  DocumentAttributes  $attributes
     */
    public function put(User $user, DocumentType $type, array $attributes): UserDocument;

    /**
     * Record a reviewer's decision against a document.
     */
    public function markReviewed(
        UserDocument $document,
        DocumentStatus $status,
        User $reviewer,
        ?string $rejectionReason,
    ): UserDocument;
}
