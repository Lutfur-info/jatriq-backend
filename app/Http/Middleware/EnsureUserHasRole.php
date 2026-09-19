<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to one or more roles, e.g. "role:Driver".
 *
 * Roles are named after the `App\enum\Role` cases, which is what the column
 * stores, so the middleware argument and the persisted value line up.
 */
class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! in_array($user->role->name, $roles, true)) {
            return response()->json([
                'message' => 'This endpoint is not available for your account.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
