<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GatewayTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('smartpark.gateway.token');

        if (! $configured || $request->header('X-Gateway-Token') !== $configured) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
