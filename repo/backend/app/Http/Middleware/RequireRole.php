<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'errors' => [],
            ], 401);
        }

        if (!in_array($request->user()->role, $roles, true)) {
            return response()->json([
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        return $next($request);
    }
}
