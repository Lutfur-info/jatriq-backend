<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared by the endpoints that are asked for a way into an account whose
 * msisdn was never verified: sign in, and forgotten password.
 *
 * Both answer the same way, because the way in is the same one - verify the
 * number. A code goes out with the refusal so the client can move straight to
 * the code field, and the response shape is what tells it to: a client reads
 * "msisdn_verified": false rather than matching on the message.
 */
trait RefusesUnverifiedMsisdn
{
    /**
     * Refuse the request and push the client to the verification screen.
     */
    private function refuseUnverifiedMsisdn(User $user, OtpService $otp): JsonResponse
    {
        if ($otp->canResend($user->msisdn)) {
            $otp->send($user);
        }

        return response()->json([
            'message' => 'Your number has not been verified yet. We have sent you a verification code.',
            'data' => [
                'msisdn' => $user->msisdn,
                'msisdn_verified' => false,
                'resend_available_in' => $otp->secondsUntilResend($user->msisdn),
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
