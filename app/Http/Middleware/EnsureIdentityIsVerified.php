<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to users whose identity documents have been accepted.
 *
 * This is the **verification badge** - `users.verification_status`, derived by
 * VerificationService from documents a reviewer approved. It is not msisdn
 * ownership (`msisdn_verified_at`, which login already enforces before it
 * issues a token) and not `is_active`. Do not conflate them.
 *
 * Enforced here rather than in a service for the same reason roles are: the
 * route says who may reach it, and a controller never repeats the check.
 *
 * The badge is returned with the refusal so a client can tell "upload your
 * documents" from "a reviewer has them" without a second request.
 */
class EnsureIdentityIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isVerified()) {
            return response()->json([
                'message' => 'Your identity has to be verified before you can do this.',
                'data' => [
                    'verification_status' => $user instanceof User
                        ? $user->verification_status->name
                        : null,
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
