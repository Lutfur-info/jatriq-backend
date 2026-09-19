<?php

namespace App\Http\Requests\Api\Verification;

use App\enum\DocumentType;
use App\Http\Requests\Concerns\ValidatesDocumentUploads;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Uploads for the verification endpoint both riding roles share.
 *
 * The accepted set follows the caller's own role rather than the route: a
 * driver and a passenger send the same three identity documents, and a driver
 * may add the licence and vehicle papers on top. A file a role cannot submit
 * is ignored rather than rejected, exactly as the role scoped endpoints did.
 */
class StoreDocumentsRequest extends FormRequest
{
    use ValidatesDocumentUploads;

    /**
     * @return array<int, DocumentType>
     */
    protected function acceptedDocuments(): array
    {
        /** @var User $user */
        $user = $this->user();

        return DocumentType::acceptableFor($user->role);
    }
}
