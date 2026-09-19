<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ShowMsisdnOtpRequest;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MsisdnOtpCodeController extends Controller
{
    /**
     * Read back the code currently held for a msisdn.
     *
     * This stands in for the SMS provider: it does not issue a code, so the
     * cooldown and attempt counter are untouched and the returned code is
     * still the one "/api/msisdn/verify" expects. Disabled outside the
     * environments allowed by "otp.expose_codes", and never on in production.
     *
     * A msisdn can hold one code per purpose, so "purpose" picks which to read
     * back - pass "PasswordReset" for the code "/api/password/reset" expects.
     */
    public function show(ShowMsisdnOtpRequest $request, OtpService $otp): JsonResponse
    {
        abort_unless($otp->exposesCodes(), 404);

        $msisdn = $request->validated('msisdn');
        $purpose = $request->purpose();
        $issued = $otp->issuedCode($msisdn, $purpose);

        if ($issued === null) {
            throw ValidationException::withMessages([
                'msisdn' => 'No active code is held for this number. Request a new one.',
            ]);
        }

        return response()->json([
            'message' => 'The code currently held for this number.',
            'data' => [
                'msisdn' => $msisdn,
                'purpose' => $purpose->name,
                ...$issued,
            ],
        ]);
    }
}
