<?php

namespace App\Http\Controllers\Api\Auth;

use App\enum\OtpPurpose;
use App\enum\OtpStatus;
use App\Http\Controllers\Concerns\RefusesUnverifiedMsisdn;
use App\Http\Controllers\Concerns\RejectsFailedOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forgotten password recovery.
 *
 * There is no email in this flow, because a msisdn is what an account is
 * identified by here: the number that receives the code is the same one that
 * signs in, so proving ownership of it is what stands in for a reset link.
 *
 * Two requests: "store" sends the code, "update" spends it on a new password.
 * The code carries the PasswordReset purpose, so it cannot be turned around
 * and used to activate an account through the verification endpoint.
 */
class PasswordResetController extends Controller
{
    use RefusesUnverifiedMsisdn, RejectsFailedOtp;

    /**
     * Send a password reset code to the account's msisdn.
     *
     * Sending costs money, so the cooldown is enforced here as well as by the
     * route's rate limiter. Calling this again is the resend.
     */
    public function store(ForgotPasswordRequest $request, OtpService $otp): JsonResponse
    {
        $user = User::query()
            ->where('msisdn', $request->validated('msisdn'))
            ->firstOrFail();

        if (($refusal = $this->refuseUnusableAccount($user, $otp)) !== null) {
            return $refusal;
        }

        $secondsUntilResend = $otp->secondsUntilResend($user->msisdn, OtpPurpose::PasswordReset);

        if ($secondsUntilResend > 0) {
            return response()->json([
                'message' => "Please wait {$secondsUntilResend} seconds before requesting another code.",
                'data' => ['resend_available_in' => $secondsUntilResend],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $code = $otp->send($user, OtpPurpose::PasswordReset);

        return response()->json([
            'message' => 'We have sent a password reset code to your number.',
            'data' => [
                'msisdn' => $user->msisdn,
                'resend_available_in' => $otp->secondsUntilResend($user->msisdn, OtpPurpose::PasswordReset),
            ],
            ...app()->isLocal() ? ['debug_code' => $code] : [],
        ]);
    }

    /**
     * Set a new password using the code that was sent to the msisdn.
     *
     * The correct code proves the number is in hand, which is the whole
     * credential here, so the user is logged straight in with a fresh token.
     * Every token issued before the reset is revoked with the old password.
     */
    public function update(ResetPasswordRequest $request, OtpService $otp): JsonResponse
    {
        $user = User::query()
            ->where('msisdn', $request->validated('msisdn'))
            ->firstOrFail();

        // Rechecked, because the account may have been closed between the two
        // requests and the code alone should not reopen it.
        if (($refusal = $this->refuseUnusableAccount($user, $otp)) !== null) {
            return $refusal;
        }

        $status = $otp->verify(
            $user->msisdn,
            $request->validated('code'),
            OtpPurpose::PasswordReset,
        );

        if ($status !== OtpStatus::Valid) {
            $this->rejectFailedOtp($status);
        }

        $user->resetPassword($request->validated('password'));

        event(new PasswordReset($user));

        return response()->json([
            'message' => 'Your password has been reset.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $user->createToken($request->deviceName())->plainTextToken,
            ],
        ]);
    }

    /**
     * Refuse an account a new password would not get anybody into.
     *
     * An unverified number has no use for a reset: verifying the code sent to
     * it signs the user in without a password at all, so they are pointed at
     * that flow instead of being handed a second way in - answered exactly as
     * login answers it, verification code and all, because it is the same
     * refusal and a client should not have to tell the two apart. A
     * deactivated account is refused for the same reason login refuses it -
     * the new password would work and the account still would not.
     */
    private function refuseUnusableAccount(User $user, OtpService $otp): ?JsonResponse
    {
        if (! $user->hasVerifiedMsisdn()) {
            return $this->refuseUnverifiedMsisdn($user, $otp);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'This account has been deactivated. Please contact support.',
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
