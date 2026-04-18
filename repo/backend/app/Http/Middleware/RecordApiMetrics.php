<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\MetricsRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Observe every API response and feed status + latency into MetricsRecorder so the
 * monitoring dashboard's p95 latency and error rate reflect real traffic.
 *
 * Internal monitoring endpoints are skipped to avoid polluting the rolling window
 * with the admin dashboard's own 10-second polling.
 */
class RecordApiMetrics
{
    public function __construct(private readonly MetricsRecorder $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $startMicro = microtime(true);
        $response   = $next($request);
        $latencyMs  = (int) round((microtime(true) - $startMicro) * 1000);

        try {
            $this->metrics->recordLatency($latencyMs);
            $this->metrics->recordRequest($response->getStatusCode());
        } catch (\Throwable) {
            // Metrics must never break a request.
        }

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        $path = $request->path(); // no leading slash

        // Don't measure the dashboard polling on itself, or the public health probe.
        return str_starts_with($path, 'api/monitoring/')
            || $path === 'api/health';
    }
}
