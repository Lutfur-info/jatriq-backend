<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\SendMsisdnOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MsisdnOtpController extends Controller
{
    /**
     * Send a fresh verification code to an unverified msisdn.
     *
     * Sending costs money, so the cooldown is enforced here as well as by
     * the route's rate limiter.
     */
    public function store(SendMsisdnOtpRequest $request, OtpService $otp): JsonResponse
    {
        $user = User::query()
            ->where('msisdn', $request->validated('msisdn'))
            ->firstOrFail();

        if ($user->hasVerifiedMsisdn()) {
            throw ValidationException::withMessages([
                'msisdn' => 'This number has already been verified.',
            ]);
        }

        $secondsUntilResend = $otp->secondsUntilResend($user->msisdn);

        if ($secondsUntilResend > 0) {
            return response()->json([
                'message' => "Please wait {$secondsUntilResend} seconds before requesting another code.",
                'data' => ['resend_available_in' => $secondsUntilResend],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $code = $otp->send($user);

        return response()->json([
            'message' => 'A new code has been sent to your number.',
            'data' => ['resend_available_in' => $otp->secondsUntilResend($user->msisdn)],
            ...app()->isLocal() ? ['debug_code' => $code] : [],
        ]);
    }
}
