<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DegradationService
{
    private const LATENCY_KEY = 'rec_latency_window';
    private const HIT_LOG_KEY = 'rec_hit_window';
    private const DISABLED_KEY = 'rec_engine_disabled';
    private const WINDOW_SECONDS = 300;           // 5-minute rolling window
    private const P95_THRESHOLD_MS = 800.0;
    private const HIT_RATE_THRESHOLD = 0.10;
    private const DISABLE_DURATION_SECONDS = 300; // disable for 5 minutes
    private const MIN_LATENCY_SAMPLES = 10;       // minimum samples in window before evaluating p95
    private const MIN_SERVED_IN_WINDOW = 5;       // minimum served batches in window before evaluating hit rate

    public function recordLatency(float $ms): void
    {
        $this->recordLatencyAt($ms, time());
    }

    public function recordLatencyAt(float $ms, int $at): void
    {
        $samples = $this->pruneToWindow(Cache::get(self::LATENCY_KEY, []), $at);
        $samples[] = ['ms' => $ms, 'at' => $at];
        Cache::put(self::LATENCY_KEY, $samples, self::WINDOW_SECONDS + 60);

        if (count($samples) >= self::MIN_LATENCY_SAMPLES) {
            $values = array_column($samples, 'ms');
            if ($this->computeP95($values) > self::P95_THRESHOLD_MS) {
                $this->disable();
            }
        }
    }

    public function recordServed(int $count, ?int $at = null): void
    {
        if ($count <= 0) {
            return;
        }
        $now = $at ?? time();
        $log = $this->pruneToWindow(Cache::get(self::HIT_LOG_KEY, []), $now);
        $log[] = ['served' => $count, 'hits' => 0, 'at' => $now];
        Cache::put(self::HIT_LOG_KEY, $log, self::WINDOW_SECONDS + 60);

        if (count($log) >= self::MIN_SERVED_IN_WINDOW) {
            $totalServed = array_sum(array_column($log, 'served'));
            $totalHits = array_sum(array_column($log, 'hits'));
            if ($totalServed > 0 && ($totalHits / $totalServed) < self::HIT_RATE_THRESHOLD) {
                $this->disable();
            }
        }
    }

    public function recordHit(?int $at = null): void
    {
        $now = $at ?? time();
        $log = $this->pruneToWindow(Cache::get(self::HIT_LOG_KEY, []), $now);
        if (empty($log)) {
            return;
        }

        $last = count($log) - 1;
        $log[$last]['hits']++;
        Cache::put(self::HIT_LOG_KEY, $log, self::WINDOW_SECONDS + 60);

        if (count($log) >= self::MIN_SERVED_IN_WINDOW) {
            $totalServed = array_sum(array_column($log, 'served'));
            $totalHits = array_sum(array_column($log, 'hits'));
            if ($totalServed > 0 && ($totalHits / $totalServed) < self::HIT_RATE_THRESHOLD) {
                $this->disable();
            }
        }
    }

    public function isDisabled(): bool
    {
        return Cache::has(self::DISABLED_KEY);
    }

    public function getP95Latency(): ?float
    {
        $samples = $this->pruneToWindow(Cache::get(self::LATENCY_KEY, []), time());
        if (empty($samples)) {
            return null;
        }
        return $this->computeP95(array_column($samples, 'ms'));
    }

    public function getHitRate(): ?float
    {
        $log = $this->pruneToWindow(Cache::get(self::HIT_LOG_KEY, []), time());
        if (empty($log)) {
            return null;
        }
        $totalServed = array_sum(array_column($log, 'served'));
        $totalHits = array_sum(array_column($log, 'hits'));
        if ($totalServed === 0) {
            return null;
        }
        return round($totalHits / $totalServed, 4);
    }

    public function getWindowSeconds(): int
    {
        return self::WINDOW_SECONDS;
    }

    private function pruneToWindow(array $entries, int $now): array
    {
        $cutoff = $now - self::WINDOW_SECONDS;
        return array_values(array_filter($entries, fn (array $e) => $e['at'] >= $cutoff));
    }

    private function disable(): void
    {
        Cache::put(self::DISABLED_KEY, true, self::DISABLE_DURATION_SECONDS);
    }

    private function computeP95(array $values): float
    {
        sort($values);
        $index = (int) ceil(0.95 * count($values)) - 1;
        return $values[max(0, $index)];
    }
}
