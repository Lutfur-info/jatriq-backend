<?php

namespace App\Http\Controllers\Api\Auth;

use App\enum\OtpStatus;
use App\Http\Controllers\Concerns\RejectsFailedOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\VerifyMsisdnRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MsisdnVerificationController extends Controller
{
    use RejectsFailedOtp;

    /**
     * Confirm ownership of a msisdn with the code that was sent to it.
     *
     * A correct code activates the account and logs the user in, so the
     * client does not have to ask for the password again.
     */
    public function store(VerifyMsisdnRequest $request, OtpService $otp): JsonResponse
    {
        $user = User::query()
            ->where('msisdn', $request->validated('msisdn'))
            ->firstOrFail();

        if ($user->hasVerifiedMsisdn()) {
            throw ValidationException::withMessages([
                'msisdn' => 'This number has already been verified.',
            ]);
        }

        $status = $otp->verify($user->msisdn, $request->validated('code'));

        if ($status !== OtpStatus::Valid) {
            $this->rejectFailedOtp($status);
        }

        $user->markMsisdnAsVerified();

        return response()->json([
            'message' => 'Your number has been verified.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $user->createToken((string) $request->userAgent())->plainTextToken,
            ],
        ]);
    }
}
