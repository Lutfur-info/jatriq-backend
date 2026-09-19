<?php

namespace App\Http\Controllers\Api\Verification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Verification\StoreDocumentsRequest;
use App\Http\Resources\VerificationResource;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The verification badge, for a driver and a passenger alike.
 *
 * Both are asked for the same identity documents - both NID pages and a
 * profile photo - so one endpoint serves them rather than one per role. A
 * driver may also send a driving licence and a vehicle registration paper;
 * they are stored and reviewed, but the badge does not wait on them.
 *
 * Nothing here awards anything. An admin reviews each document and
 * VerificationService derives the badge from those decisions.
 */
class VerificationController extends Controller
{
    /**
     * Show the caller's badge, what they have submitted and what is missing.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'message' => 'Verification status.',
            'data' => new VerificationResource($user->load('documents')),
        ]);
    }

    /**
     * Upload one or more documents.
     *
     * Any document already held for the same type is replaced and goes back
     * into review, and the badge is recomputed from the whole set.
     */
    public function store(StoreDocumentsRequest $request, VerificationService $verification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $verification->storeMany($user, $request->uploadedDocuments());

        return response()->json([
            'message' => 'Your documents have been uploaded and are awaiting review.',
            'data' => new VerificationResource($user->load('documents')),
        ]);
    }
}
