<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'errors' => [],
            ], 401);
        }

        $rawKey = substr($header, 7);
        $device = Device::findByApiKey($rawKey);

        if ($device === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'errors' => [],
            ], 401);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
