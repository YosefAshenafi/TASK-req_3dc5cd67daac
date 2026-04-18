<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests whose authenticated user has been blacklisted or frozen since the
 * token was issued. Login-time checks are not enough — a user who logs in, then
 * gets frozen mid-session, must stop making successful requests immediately.
 */
class EnforceAccountStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->blacklisted_at !== null) {
                // Defensive: the blacklist admin action revokes tokens, but if a token
                // somehow survives, treat it as invalid.
                return response()->json([
                    'message'     => 'This account has been disabled.',
                    'reason_code' => 'account_blacklisted',
                ], 401);
            }

            if ($user->frozen_until !== null && $user->frozen_until->isFuture()) {
                return response()->json([
                    'message'      => 'Account is temporarily frozen.',
                    'frozen_until' => $user->frozen_until->toIso8601String(),
                    'reason_code'  => 'account_frozen',
                ], 423);
            }
        }

        return $next($request);
    }
}
