<?php

namespace App\Console\Commands;

use App\Models\FeatureFlag;
use App\Services\Monitoring\MetricsRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitoringSample extends Command
{
    protected $signature = 'app:monitoring-sample';
    protected $description = 'Sample system metrics and check circuit breaker state every 30 seconds.';

    public function handle(MetricsRecorder $metrics): int
    {
        try {
            $latencyThresholdMs = (int) config('smartpark.latency_p95_threshold_ms', 800);
            $windowMinutes      = (int) config('smartpark.latency_window_minutes', 5);
            $hitRateMin         = (float) config('smartpark.recommendation_hit_rate_min', 0.10);
            $recoveryMinutes    = (int) config('smartpark.circuit_breaker_recovery_minutes', 15);
            $now                = time();

            // --- Compute p95 latency via MetricsRecorder ---
            $p95Ms       = $metrics->readLatencyP95($windowMinutes);
            $p95Breached = $p95Ms > 0 && $p95Ms > $latencyThresholdMs;

            // --- Compute recommendation hit rate via MetricsRecorder ---
            $counts          = $metrics->readRecommendationCounts();
            $hitRateBreached = $counts['requests'] >= 20
                && $counts['requests'] > 0
                && ($counts['hits'] / $counts['requests']) < $hitRateMin;

            // --- Also check legacy failure counter ---
            $failureCount    = (int) Cache::get('circuit_breaker:recommendation_failures', 0);
            $legacyThreshold = (int) config('smartpark.circuit_breaker_threshold', 10);
            $legacyBreached  = $failureCount >= $legacyThreshold;

            $flag = FeatureFlag::find('recommended_enabled');
            if (! $flag) {
                return Command::SUCCESS;
            }

            $shouldDisable = $p95Breached || $hitRateBreached || $legacyBreached;

            if ($shouldDisable && $flag->enabled) {
                $reason = match (true) {
                    $p95Breached     => "p95 latency exceeded {$latencyThresholdMs}ms threshold.",
                    $hitRateBreached => 'Recommendation hit rate below ' . ($hitRateMin * 100) . '%.',
                    default          => "Circuit breaker: {$failureCount} failures.",
                };
                $flag->enabled    = false;
                $flag->reason     = $reason;
                $flag->updated_at = now();
                $flag->save();
                Cache::put('circuit_breaker:degraded_since', $now, 3600);
                Log::warning("MonitoringSample: Disabled recommended_enabled. Reason: {$reason}");

            } elseif (! $flag->enabled && ! $shouldDisable) {
                $degradedSince   = (int) Cache::get('circuit_breaker:degraded_since', $now);
                $minutesDegraded = ($now - $degradedSince) / 60;
                if ($minutesDegraded >= $recoveryMinutes) {
                    $flag->enabled    = true;
                    $flag->reason     = 'Auto-recovered after healthy metrics window.';
                    $flag->updated_at = now();
                    $flag->save();
                    Cache::forget('circuit_breaker:degraded_since');
                    Cache::put('circuit_breaker:recommendation_failures', 0, 3600);
                    $metrics->resetRecommendationCounters();
                    Log::info('MonitoringSample: Auto-recovered recommended_enabled flag.');
                }
            }

            Cache::put('monitoring:last_sample_at', now()->toIso8601String(), 120);
            $this->info('Monitoring sample completed.');

        } catch (\Throwable $e) {
            Log::error("MonitoringSample: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
