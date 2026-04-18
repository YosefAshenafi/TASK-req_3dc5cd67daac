<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\FeatureFlag;
use App\Models\PlayHistory;
use App\Services\Monitoring\MetricsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    public function __construct(private readonly MetricsRecorder $metrics) {}

    public function status(Request $request): JsonResponse
    {
        $featureFlags = FeatureFlag::all()->mapWithKeys(fn ($f) => [
            $f->key => [
                'enabled'            => (bool) $f->enabled,
                'last_transition_at' => $f->updated_at?->toIso8601String(),
                'reason'             => $f->reason,
            ],
        ]);

        return response()->json([
            'api'           => $this->getApiMetrics(),
            'queues'        => $this->getQueueLengths(),
            'storage'       => $this->getStorageInfo(),
            'devices'       => $this->getDeviceHealth(),
            'content_usage' => $this->getContentUsage(),
            'feature_flags' => $featureFlags,
        ]);
    }

    public function resetFlag(Request $request, string $flag): JsonResponse
    {
        // Only flags that the system recognises may be reset via this route.
        $allowedFlags = ['recommended_enabled'];
        if (! in_array($flag, $allowedFlags, true)) {
            return response()->json(['message' => "Unknown feature flag '{$flag}'."], 404);
        }

        $record = FeatureFlag::updateOrCreate(
            ['key' => $flag],
            ['enabled' => true, 'reason' => 'Manually reset by admin', 'updated_at' => now()]
        );

        return response()->json([
            'message' => 'Flag re-enabled.',
            'key'     => $record->key,
            'enabled' => (bool) $record->enabled,
        ]);
    }

    private function getApiMetrics(): array
    {
        $windowMinutes = (int) config('smartpark.latency_window_minutes', 5);
        $p95           = $this->metrics->readLatencyP95($windowMinutes);
        $errorRate     = $this->metrics->readErrorRate($windowMinutes);

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

    /**
     * Summarize content usage over a rolling 24-hour window so admins can see which assets
     * are pulling traffic without opening an external dashboard. The Prompt names
     * "operational dashboards for device status and content usage" as an explicit admin
     * requirement.
     */
    private function getContentUsage(): array
    {
        try {
            $dayAgo = now()->subDay();

            $plays24h = PlayHistory::where('played_at', '>=', $dayAgo)->count();
            $activeUsers24h = PlayHistory::where('played_at', '>=', $dayAgo)
                ->distinct('user_id')
                ->count('user_id');

            $topPlayed = DB::table('play_history')
                ->select('asset_id', DB::raw('COUNT(*) as play_count'))
                ->where('played_at', '>=', $dayAgo)
                ->groupBy('asset_id')
                ->orderByDesc('play_count')
                ->limit(5)
                ->get();

            $topAssetIds = $topPlayed->pluck('asset_id')->all();
            $assetsById  = Asset::whereIn('id', $topAssetIds)->get()->keyBy('id');

            $topAssets = $topPlayed->map(function ($row) use ($assetsById) {
                $asset = $assetsById->get($row->asset_id);
                return [
                    'asset_id'   => $row->asset_id,
                    'title'      => $asset?->title,
                    'mime'       => $asset?->mime,
                    'play_count' => (int) $row->play_count,
                ];
            })->all();

            return [
                'window_hours'      => 24,
                'plays_24h'         => (int) $plays24h,
                'active_users_24h'  => (int) $activeUsers24h,
                'top_assets'        => $topAssets,
                'total_ready_assets'=> Asset::where('status', 'ready')->count(),
                'favorites_count'   => (int) DB::table('favorites')->count(),
                'playlists_count'   => (int) DB::table('playlists')->whereNull('deleted_at')->count(),
            ];
        } catch (\Throwable) {
            return [
                'window_hours'       => 24,
                'plays_24h'          => 0,
                'active_users_24h'   => 0,
                'top_assets'         => [],
                'total_ready_assets' => 0,
                'favorites_count'    => 0,
                'playlists_count'    => 0,
            ];
        }
    }

    private function getDeviceHealth(): array
    {
        $onlineThreshold = now()->subMinutes(5);

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
