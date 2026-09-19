<?php

namespace App\Http\Controllers\Api\Verification;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFileController extends Controller
{
    /**
     * Stream a submitted document back to its owner or a reviewer.
     *
     * NID scans are held on a private disk with no public URL, so this route
     * is the only way to read one and it re-checks who is asking every time.
     */
    public function show(Request $request, UserDocument $document, FilesystemFactory $filesystem): StreamedResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        abort_unless($viewer->isAdmin() || $document->user_id === $viewer->getKey(), 403);

        $disk = $filesystem->disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, $document->original_name);
    }
}
