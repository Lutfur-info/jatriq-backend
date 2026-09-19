<?php

namespace App\Http\Resources;

use App\Models\UserDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserDocument
 */
class UserDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The file itself is never given a public URL: "url" points at the
     * authenticated download route, which only the owner or an admin may call.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->name,
            'label' => $this->type->label(),
            'status' => $this->status->name,
            'rejection_reason' => $this->rejection_reason,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => route('api.documents.show', ['document' => $this->id]),
            'reviewed_at' => $this->reviewed_at,
            'uploaded_at' => $this->created_at,
        ];
    }
}
