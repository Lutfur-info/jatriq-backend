<?php

namespace App\Http\Requests\Concerns;

use App\enum\DocumentType;
use Illuminate\Validation\Rules\File;

/**
 * The size and type rules an uploaded document has to satisfy.
 *
 * Shared by the verification endpoint and the driver's vehicle endpoint, so
 * the limits in config/verification.php apply wherever a scan is accepted
 * rather than being restated per endpoint.
 */
trait BuildsDocumentFileRules
{
    /**
     * A profile photo has to render beside the badge, so it must be an image.
     * Scans of a NID or licence are commonly sent as a PDF.
     */
    protected function fileRuleFor(DocumentType $type): File
    {
        $maxKilobytes = (int) config('verification.max_size');

        /** @var array<int, string> $mimes */
        $mimes = config('verification.mimes');

        return $type->isPhoto()
            ? File::image()->max($maxKilobytes)
            : File::types($mimes)->max($maxKilobytes);
    }
}
