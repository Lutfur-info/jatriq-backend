<?php

namespace App\Http\Controllers\Concerns;

use App\enum\OtpStatus;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared by every endpoint that spends a one time code, so a wrong code
 * reads the same to a client whether it was guarding a msisdn or a password.
 */
trait RejectsFailedOtp
{
    /**
     * Turn a failed verification into the matching HTTP response.
     *
     * @throws ValidationException
     */
    private function rejectFailedOtp(OtpStatus $status): never
    {
        throw match ($status) {
            OtpStatus::TooManyAttempts => new HttpResponseException(response()->json([
                'message' => 'Too many incorrect attempts. Please request a new code.',
            ], Response::HTTP_TOO_MANY_REQUESTS)),
            OtpStatus::Expired => ValidationException::withMessages([
                'code' => 'This code has expired. Please request a new one.',
            ]),
            default => ValidationException::withMessages([
                'code' => 'The code you entered is incorrect.',
            ]),
        };
    }
}
