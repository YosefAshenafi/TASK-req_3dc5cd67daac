<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\FeatureFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $featureFlags = FeatureFlag::all()->mapWithKeys(fn ($f) => [
            $f->key => [
                'enabled'           => (bool) $f->enabled,
                'last_transition_at' => $f->updated_at?->toIso8601String(),
                'reason'            => $f->reason,
            ],
        ]);

        return response()->json([
            'api'           => $this->getApiMetrics(),
            'queues'        => $this->getQueueLengths(),
            'storage'       => $this->getStorageInfo(),
            'devices'       => $this->getDeviceHealth(),
            'feature_flags' => $featureFlags,
        ]);
    }

    public function resetRecommended(Request $request): JsonResponse
    {
        $flag = FeatureFlag::updateOrCreate(
            ['key' => 'recommended_enabled'],
            ['enabled' => true, 'reason' => 'Manually reset by admin', 'updated_at' => now()]
        );

        return response()->json(['message' => 'Flag re-enabled.', 'enabled' => $flag->enabled]);
    }

    private function getApiMetrics(): array
    {
        try {
            $redis = app('redis')->connection();
            $samples = $redis->lrange('api_latency_samples', 0, -1);
            if (count($samples) > 0) {
                $sorted = collect($samples)->map(fn ($s) => (float) $s)->sort()->values();
                $p95idx = (int) ceil(count($sorted) * 0.95) - 1;
                $p95    = $sorted->get(max(0, $p95idx), 0);
            } else {
                $p95 = 0;
            }
            $errors    = (int) ($redis->get('api_error_count_5m') ?? 0);
            $requests  = (int) ($redis->get('api_request_count_5m') ?? 0);
            $errorRate = $requests > 0 ? round($errors / $requests, 4) : 0;
        } catch (\Throwable) {
            $p95       = 0;
            $errorRate = 0;
        }

        return ['p95_ms_5m' => $p95, 'error_rate_5m' => $errorRate];
    }

    private function getQueueLengths(): array
    {
        try {
            $redis = app('redis')->connection();
            return [
                'default'    => (int) $redis->llen('queues:default'),
                'indexing'   => (int) $redis->llen('queues:indexing'),
                'thumbnails' => (int) $redis->llen('queues:thumbnails'),
            ];
        } catch (\Throwable) {
            return ['default' => 0, 'indexing' => 0, 'thumbnails' => 0];
        }
    }

    private function getStorageInfo(): array
    {
        try {
            $storagePath = storage_path('app');
            $totalBytes  = disk_total_space($storagePath) ?: 0;
            $freeBytes   = disk_free_space($storagePath) ?: 0;
            $usedBytes   = $totalBytes - $freeBytes;
            $usedPct     = $totalBytes > 0 ? round($usedBytes / $totalBytes * 100, 2) : 0;
        } catch (\Throwable) {
            $freeBytes = 0;
            $usedPct   = 0;
        }

        return [
            'media_volume_free_bytes' => $freeBytes,
            'media_volume_used_pct'   => $usedPct,
        ];
    }

    private function getDeviceHealth(): array
    {
        $onlineThreshold  = now()->subMinutes(5);
        $offlineThreshold = now()->subMinutes(5);

        $online  = Device::where('last_seen_at', '>=', $onlineThreshold)->count();
        $total   = Device::count();
        $offline = $total - $online;

        try {
            $hourAgo    = now()->subHour();
            $hourEvents = DeviceEvent::where('received_at', '>=', $hourAgo)->count();
            $hourDups   = DeviceEvent::where('received_at', '>=', $hourAgo)
                ->where('is_out_of_order', false)
                ->whereIn('idempotency_key', function ($q) use ($hourAgo) {
                    $q->select('idempotency_key')
                      ->from('device_events')
                      ->where('received_at', '<', $hourAgo);
                })
                ->count();
            $dedupRate = $hourEvents > 0 ? round($hourDups / $hourEvents, 4) : 0;
        } catch (\Throwable) {
            $dedupRate = 0;
        }

        return [
            'online'        => $online,
            'offline'       => $offline,
            'dedup_rate_1h' => $dedupRate,
        ];
    }
}
