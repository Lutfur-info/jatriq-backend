<?php

namespace App\Http\Resources;

use App\enum\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user's verification badge together with the documents behind it.
 *
 * @mixin User
 */
class VerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $required = DocumentType::requiredFor($this->role);
        $submitted = $this->documents->keyBy(fn (UserDocument $document): string => $document->type->name);

        $missing = array_values(array_filter(
            $required,
            fn (DocumentType $type): bool => ! $submitted->has($type->name),
        ));

        return [
            'user_id' => $this->id,
            'full_name' => $this->full_name,
            'msisdn' => $this->msisdn,
            'role' => $this->role->name,
            'status' => $this->verification_status->name,
            'verified' => $this->isVerified(),
            'verified_at' => $this->verified_at,
            'required_documents' => array_column($required, 'name'),
            'missing_documents' => array_column($missing, 'name'),
            'documents' => UserDocumentResource::collection($this->documents),
        ];
    }
}
