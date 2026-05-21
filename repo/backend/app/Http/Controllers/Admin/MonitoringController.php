<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DegradationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    public function __construct(private readonly DegradationService $degradation) {}

    public function index(): JsonResponse
    {
        $dbHealthy = $this->checkDatabase();
        $queueBacklog = $this->getQueueBacklog();
        $failedJobs = $this->getFailedJobsCount();
        $apiErrorsLastHour = $this->getApiErrorsLastHour();
        $deviceIngestion = $this->getDeviceIngestionHealth();

        return response()->json([
            'data' => [
                'health' => [
                    'database' => $dbHealthy ? 'healthy' : 'unhealthy',
                    'queue' => $queueBacklog < 1000 ? 'healthy' : 'degraded',
                ],
                'queue' => [
                    'pending_jobs' => $queueBacklog,
                    'failed_jobs' => $failedJobs,
                ],
                'recommendations' => [
                    'engine_status' => $this->degradation->isDisabled() ? 'disabled_fallback' : 'active',
                    'window_seconds' => $this->degradation->getWindowSeconds(),
                    'p95_latency_ms' => $this->degradation->getP95Latency(),
                    'hit_rate' => $this->degradation->getHitRate(),
                    'p95_threshold_ms' => 800.0,
                    'hit_rate_threshold' => 0.10,
                ],
                'api_errors' => [
                    'last_hour' => $apiErrorsLastHour,
                ],
                'device_ingestion' => $deviceIngestion,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function getQueueBacklog(): int
    {
        try {
            return (int) DB::table('jobs')->count();
        } catch (\Throwable) {
            return -1;
        }
    }

    private function getFailedJobsCount(): int
    {
        try {
            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return -1;
        }
    }

    private function getApiErrorsLastHour(): int
    {
        $currentHour = 'api_errors_' . date('YmdH');
        $prevHour = 'api_errors_' . date('YmdH', strtotime('-1 hour'));
        return (int) Cache::get($currentHour, 0) + (int) Cache::get($prevHour, 0);
    }

    private function getDeviceIngestionHealth(): array
    {
        try {
            $since = now()->subHours(24);
            $counts = DB::table('device_events')
                ->where('received_at', '>=', $since)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status')
                ->toArray();

            return [
                'received_count' => (int) ($counts['received'] ?? 0),
                'late_count' => (int) ($counts['late'] ?? 0),
                'buffered_count' => (int) ($counts['buffered'] ?? 0),
                'duplicate_count' => (int) ($counts['duplicate'] ?? 0),
            ];
        } catch (\Throwable) {
            return [
                'received_count' => -1,
                'late_count' => -1,
                'buffered_count' => -1,
                'duplicate_count' => -1,
            ];
        }
    }
}
