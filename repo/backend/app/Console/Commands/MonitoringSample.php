<?php

namespace App\Console\Commands;

use App\Models\FeatureFlag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitoringSample extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:monitoring-sample';

    /**
     * The console command description.
     */
    protected $description = 'Sample system metrics and check circuit breaker state every 30 seconds.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $latencyThresholdMs = (int) config('smartpark.latency_p95_threshold_ms', 800);
            $windowMinutes      = (int) config('smartpark.latency_window_minutes', 5);
            $hitRateMin         = (float) config('smartpark.recommendation_hit_rate_min', 0.10);
            $recoveryMinutes    = (int) config('smartpark.circuit_breaker_recovery_minutes', 15);
            $now                = time();
            $windowSeconds      = $windowMinutes * 60;

            // --- Compute p95 latency from the rolling window ---
            $samples = Cache::getRedis()->zRangeByScore(
                'monitoring:latency_samples',
                $now - $windowSeconds,
                '+inf'
            );
            $latencies = collect($samples)
                ->map(fn ($s) => (int) explode(':', $s)[1])
                ->sort()
                ->values();

            $p95Breached = false;
            if ($latencies->count() >= 5) {
                $p95Index    = (int) ceil(0.95 * $latencies->count()) - 1;
                $p95Ms       = $latencies[$p95Index];
                $p95Breached = $p95Ms > $latencyThresholdMs;
            }

            // --- Compute recommendation hit rate ---
            $requests        = (int) Cache::get('monitoring:recommendation_requests', 0);
            $hits            = (int) Cache::get('monitoring:recommendation_hits', 0);
            $hitRateBreached = $requests >= 20 && ($hits / $requests) < $hitRateMin;

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
                    Cache::put('monitoring:recommendation_requests', 0, 3600);
                    Cache::put('monitoring:recommendation_hits', 0, 3600);
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
