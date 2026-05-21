<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StructuredLogger
{
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'token', 'secret', 'api_key', 'authorization'];

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $statusCode = $response->getStatusCode();

        Log::info('http_request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $statusCode,
            'duration_ms' => $duration,
            'ip' => $request->ip(),
        ]);

        if ($statusCode >= 400) {
            $cacheKey = 'api_errors_' . date('YmdH');
            Cache::increment($cacheKey);
            Cache::put($cacheKey, Cache::get($cacheKey, 0), 7200);
        }

        return $response;
    }

    public static function maskSensitive(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $value = '[REDACTED]';
            } elseif (is_array($value)) {
                $value = self::maskSensitive($value);
            }
        }
        return $data;
    }
}
