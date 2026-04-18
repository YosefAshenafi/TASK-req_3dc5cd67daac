<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;

class MetricsRecorder
{
    const LATENCY_KEY         = 'monitoring:latency_samples';
    const REC_REQUESTS_KEY    = 'monitoring:recommendation_requests';
    const REC_HITS_KEY        = 'monitoring:recommendation_hits';
    const REQUEST_SAMPLES_KEY = 'monitoring:request_samples';

    public function recordLatency(int $ms): void
    {
        $now           = time();
        $windowSeconds = (int) config('smartpark.latency_window_minutes', 5) * 60;
        try {
            $redis  = Cache::getRedis();
            // Include a random suffix so same-second same-ms samples don't overwrite each other.
            $member = "{$now}:{$ms}:" . bin2hex(random_bytes(4));
            $redis->zAdd(self::LATENCY_KEY, $now, $member);
            $redis->zRemRangeByScore(self::LATENCY_KEY, '-inf', $now - $windowSeconds);
        } catch (\Throwable) {
            // Redis unavailable — silently skip
        }
    }

    /**
     * Record an API request outcome. 5xx responses count as errors for the rolling
     * error-rate metric; 4xx is client fault and is excluded.
     */
    public function recordRequest(int $statusCode): void
    {
        $now           = time();
        $windowSeconds = (int) config('smartpark.latency_window_minutes', 5) * 60;
        $isError       = $statusCode >= 500 ? 1 : 0;

        try {
            $redis = Cache::getRedis();
            // Use a unique member per sample so zAdd doesn't overwrite prior samples at the same second.
            $member = "{$now}:{$statusCode}:" . bin2hex(random_bytes(4));
            $redis->zAdd(self::REQUEST_SAMPLES_KEY, $now, $member);
            $redis->zRemRangeByScore(self::REQUEST_SAMPLES_KEY, '-inf', $now - $windowSeconds);
            unset($isError); // silence unused var in error-path fallback below
        } catch (\Throwable) {
            // Redis unavailable — silently skip; error rate will report 0.
        }
    }

    /**
     * Compute the rolling error rate (fraction of requests with status >= 500) over the
     * last N minutes. Returns 0 if there are no samples or the store is unavailable.
     */
    public function readErrorRate(int $windowMinutes = 5): float
    {
        $now = time();
        try {
            $samples = Cache::getRedis()->zRangeByScore(
                self::REQUEST_SAMPLES_KEY,
                $now - $windowMinutes * 60,
                '+inf'
            );
        } catch (\Throwable) {
            return 0;
        }

        if (empty($samples)) {
            return 0;
        }

        $total  = count($samples);
        $errors = 0;
        foreach ($samples as $sample) {
            // Format: "<ts>:<status>:<random>"
            $parts = explode(':', $sample);
            $status = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($status >= 500) {
                $errors++;
            }
        }

        return round($errors / $total, 4);
    }

    public function readLatencyP95(int $windowMinutes = 5): float
    {
        $now = time();
        try {
            $samples = Cache::getRedis()->zRangeByScore(
                self::LATENCY_KEY,
                $now - $windowMinutes * 60,
                '+inf'
            );
        } catch (\Throwable) {
            return 0;
        }

        if (empty($samples)) {
            return 0;
        }

        $latencies = collect($samples)
            ->map(fn ($s) => (float) explode(':', $s)[1])
            ->sort()
            ->values();

        if ($latencies->count() < 5) {
            return 0;
        }

        $p95idx = (int) ceil(0.95 * $latencies->count()) - 1;

        return $latencies[$p95idx];
    }

    public function incrementRecommendationRequests(): void
    {
        $now           = time();
        $windowSeconds = (int) config('smartpark.latency_window_minutes', 5) * 60;
        try {
            $redis  = Cache::getRedis();
            $member = "{$now}:" . bin2hex(random_bytes(4));
            $redis->zAdd(self::REC_REQUESTS_KEY, $now, $member);
            $redis->zRemRangeByScore(self::REC_REQUESTS_KEY, '-inf', $now - $windowSeconds);
        } catch (\Throwable) {
        }
    }

    public function incrementRecommendationHits(): void
    {
        $now           = time();
        $windowSeconds = (int) config('smartpark.latency_window_minutes', 5) * 60;
        try {
            $redis  = Cache::getRedis();
            $member = "{$now}:" . bin2hex(random_bytes(4));
            $redis->zAdd(self::REC_HITS_KEY, $now, $member);
            $redis->zRemRangeByScore(self::REC_HITS_KEY, '-inf', $now - $windowSeconds);
        } catch (\Throwable) {
        }
    }

    public function readRecommendationCounts(): array
    {
        $now           = time();
        $windowSeconds = (int) config('smartpark.latency_window_minutes', 5) * 60;
        try {
            $redis    = Cache::getRedis();
            $requests = (int) $redis->zCount(self::REC_REQUESTS_KEY, $now - $windowSeconds, '+inf');
            $hits     = (int) $redis->zCount(self::REC_HITS_KEY, $now - $windowSeconds, '+inf');
            return ['requests' => $requests, 'hits' => $hits];
        } catch (\Throwable) {
            return ['requests' => 0, 'hits' => 0];
        }
    }

    public function readRecommendationHitRate(): float
    {
        $counts = $this->readRecommendationCounts();
        if ($counts['requests'] === 0) return 0;
        return round($counts['hits'] / $counts['requests'], 4);
    }

    public function resetRecommendationCounters(): void
    {
        try {
            $redis = Cache::getRedis();
            $redis->del(self::REC_REQUESTS_KEY);
            $redis->del(self::REC_HITS_KEY);
        } catch (\Throwable) {
            Cache::forget(self::REC_REQUESTS_KEY);
            Cache::forget(self::REC_HITS_KEY);
        }
    }
}
