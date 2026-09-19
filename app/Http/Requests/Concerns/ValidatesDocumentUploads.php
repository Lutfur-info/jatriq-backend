<?php

namespace App\Http\Requests\Concerns;

use App\enum\DocumentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Shared upload rules for the role specific verification endpoints.
 *
 * Every document is optional so a client may submit them one at a time over a
 * poor connection, but a request that carries no file at all is rejected -
 * the badge only moves once the whole set is in.
 */
trait ValidatesDocumentUploads
{
    use BuildsDocumentFileRules;

    /**
     * The documents this endpoint accepts.
     *
     * @return array<int, DocumentType>
     */
    abstract protected function acceptedDocuments(): array;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->acceptedDocuments() as $type) {
            $rules[$type->field()] = ['nullable', $this->fileRuleFor($type)];
        }

        return $rules;
    }

    /**
     * Reject a request that submitted nothing.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->uploadedDocuments() === []) {
                    $validator->errors()->add('documents', 'Attach at least one document to upload.');
                }
            },
        ];
    }

    /**
     * Human readable field names for the validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->acceptedDocuments() as $type) {
            $attributes[$type->field()] = $type->label();
        }

        return $attributes;
    }

    /**
     * The files this request actually carried, keyed by DocumentType name.
     *
     * @return array<string, UploadedFile>
     */
    public function uploadedDocuments(): array
    {
        $files = [];

        foreach ($this->acceptedDocuments() as $type) {
            $file = $this->file($type->field());

            if ($file instanceof UploadedFile) {
                $files[$type->name] = $file;
            }
        }

        return $files;
    }
}
