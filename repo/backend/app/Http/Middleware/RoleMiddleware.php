<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Accepts a comma-separated list of allowed roles, e.g. role:admin,technician
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Flatten: roles may be passed as individual args or comma-separated in one arg
        $allowedRoles = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $r) {
                $allowedRoles[] = trim($r);
            }
        }

        if (! in_array($user->role, $allowedRoles, true)) {
            return response()->json([
                'message' => 'Forbidden. Insufficient permissions.',
            ], 403);
        }

        return $next($request);
    }
}
