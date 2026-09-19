<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Concerns\RefusesUnverifiedMsisdn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    use RefusesUnverifiedMsisdn;

    /**
     * Exchange a msisdn and password for a Sanctum token.
     */
    public function store(LoginRequest $request, OtpService $otp): JsonResponse
    {
        $user = User::query()
            ->where('msisdn', $request->validated('msisdn'))
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'msisdn' => __('auth.failed'),
            ]);
        }

        if (! $user->hasVerifiedMsisdn()) {
            return $this->refuseUnverifiedMsisdn($user, $otp);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'This account has been deactivated. Please contact support.',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'Logged in.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $user->createToken($request->deviceName())->plainTextToken,
            ],
        ]);
    }

    /**
     * Revoke the token that authenticated the current request.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
