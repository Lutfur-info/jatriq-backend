<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    /**
     * Register an account and send a verification code to its msisdn.
     *
     * No token is issued here. The account stays inactive until the code
     * is confirmed through the msisdn verification endpoint.
     */
    public function store(RegisterRequest $request, OtpService $otp): JsonResponse
    {
        $user = new User($request->attributesForUser());
        $user->role = $request->role();
        $user->save();

        // Pull in the column defaults applied on insert, such as gender.
        $user->refresh();

        $code = $otp->send($user);

        return response()->json([
            'message' => 'Registration successful. Enter the code we sent to your number to activate your account.',
            'data' => [
                'user' => new UserResource($user),
                'resend_available_in' => $otp->secondsUntilResend($user->msisdn),
            ],
            ...app()->isLocal() ? ['debug_code' => $code] : [],
        ], Response::HTTP_CREATED);
    }
}
