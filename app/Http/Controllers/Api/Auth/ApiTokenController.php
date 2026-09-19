<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\StoreApiTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenController extends Controller
{
    /**
     * Issue an additional token to the already authenticated user.
     *
     * The token that authorised this request keeps working, so a client can
     * name a token per device without asking for the password again.
     */
    public function store(StoreApiTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->is_active) {
            return response()->json([
                'message' => 'This account has been deactivated. Please contact support.',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $user->createToken($request->deviceName());

        return response()->json([
            'message' => 'Token created.',
            'data' => [
                'id' => $token->accessToken->getKey(),
                'device_name' => $token->accessToken->name,
                'token' => $token->plainTextToken,
            ],
        ], Response::HTTP_CREATED);
    }
}
