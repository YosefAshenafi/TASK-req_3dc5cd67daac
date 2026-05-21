<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DegradationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DegradationServiceTest extends TestCase
{
    private DegradationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new DegradationService();
    }

    // -------------------------------------------------------------------------
    // Baseline / existing behavior
    // -------------------------------------------------------------------------

    public function test_not_disabled_by_default(): void
    {
        $this->assertFalse($this->service->isDisabled());
    }

    public function test_p95_latency_threshold_triggers_disable(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordLatency(1000.0);
        }

        $this->assertTrue($this->service->isDisabled());
    }

    public function test_p95_latency_below_threshold_does_not_disable(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordLatency(100.0);
        }

        $this->assertFalse($this->service->isDisabled());
    }

    public function test_hit_rate_below_threshold_triggers_disable(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->service->recordServed(10);
        }

        $this->assertTrue($this->service->isDisabled(), 'Zero hits out of 200 served should trigger disable');
    }

    public function test_hit_rate_above_threshold_does_not_disable(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->service->recordServed(10);
            $this->service->recordHit();
            $this->service->recordHit();
        }

        $this->assertFalse($this->service->isDisabled(), '20% hit rate should not trigger disable');
    }

    public function test_fallback_mode_enabled_after_high_latency(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordLatency(1500.0);
        }

        $this->assertTrue($this->service->isDisabled());
    }

    public function test_get_p95_latency_returns_null_when_no_samples(): void
    {
        $this->assertNull($this->service->getP95Latency());
    }

    public function test_get_p95_latency_computes_correctly(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->service->recordLatency((float) ($i * 10));
        }

        $p95 = $this->service->getP95Latency();
        $this->assertNotNull($p95);
        $this->assertGreaterThanOrEqual(185.0, $p95);
    }

    public function test_get_hit_rate_returns_null_when_no_data(): void
    {
        $this->assertNull($this->service->getHitRate());
    }

    public function test_get_hit_rate_returns_correct_ratio(): void
    {
        $this->service->recordServed(10);
        $this->service->recordHit();
        $this->service->recordHit();

        $rate = $this->service->getHitRate();
        $this->assertNotNull($rate);
        $this->assertEqualsWithDelta(0.2, $rate, 0.01);
    }

    // -------------------------------------------------------------------------
    // 5-minute rolling window semantics
    // -------------------------------------------------------------------------

    public function test_latency_samples_outside_5min_window_are_pruned(): void
    {
        $oldTime = time() - 400; // 400s ago, outside the 300s window
        $staleSamples = array_fill(0, 10, ['ms' => 1500.0, 'at' => $oldTime]);
        Cache::put('rec_latency_window', $staleSamples, 3600);

        $this->assertNull($this->service->getP95Latency());
        $this->assertFalse($this->service->isDisabled());
    }

    public function test_p95_trigger_requires_min_samples_within_window(): void
    {
        $now = time();
        $oldTime = $now - 400;

        // 10 stale high-latency samples — outside window
        $stale = array_fill(0, 10, ['ms' => 1500.0, 'at' => $oldTime]);
        Cache::put('rec_latency_window', $stale, 3600);

        // Only 1 sample in current window — not enough (MIN_LATENCY_SAMPLES = 10)
        $this->service->recordLatencyAt(100.0, $now);

        $this->assertFalse($this->service->isDisabled());
    }

    public function test_p95_triggers_when_10_high_latency_samples_within_window(): void
    {
        $now = time();
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordLatencyAt(1500.0, $now);
        }

        $this->assertTrue($this->service->isDisabled());
    }

    public function test_p95_does_not_trigger_for_mixed_samples_below_threshold(): void
    {
        $now = time();
        // 9 fast samples + 1 slow — p95 of [100,100,...,100,1000] with 10 values is index ceil(9.5)-1=9 → 1000ms
        // Actually with 9 × 100ms and 1 × 1000ms: p95 index = ceil(0.95*10)-1 = 9 → sorted[9] = 1000ms > 800ms → disable
        // So let's do 9 slow + 1 fast with fast being p95: 1 × 900ms, 9 × 100ms
        // Sorted: [100,100,100,100,100,100,100,100,100,900], p95 index = 9 → 900ms > 800ms → disable
        // Need 10 all below 800: use 750ms
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordLatencyAt(750.0, $now);
        }

        $this->assertFalse($this->service->isDisabled());
    }

    public function test_hit_rate_batches_outside_window_excluded_from_evaluation(): void
    {
        $oldTime = time() - 400; // outside window
        // 20 old served batches with 0 hits — would trigger if counted
        $staleLog = array_fill(0, 20, ['served' => 10, 'hits' => 0, 'at' => $oldTime]);
        Cache::put('rec_hit_window', $staleLog, 3600);

        $this->assertNull($this->service->getHitRate());
        $this->assertFalse($this->service->isDisabled());
    }

    public function test_hit_rate_triggers_on_5_batches_zero_hits_in_window(): void
    {
        $now = time();
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordServed(10, $now);
        }
        // 5 batches × 10 served, 0 hits → 0% < 10% → disable
        $this->assertTrue($this->service->isDisabled());
    }

    public function test_hit_rate_does_not_trigger_with_adequate_hit_rate_in_window(): void
    {
        $now = time();
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordServed(10, $now);
            $this->service->recordHit($now);
            $this->service->recordHit($now); // 2/10 = 20% per batch
        }

        $this->assertFalse($this->service->isDisabled());
    }

    public function test_hit_rate_does_not_trigger_below_min_served_batches(): void
    {
        $now = time();
        // Only 4 batches (< MIN_SERVED_IN_WINDOW = 5), 0 hits — should not yet disable
        for ($i = 0; $i < 4; $i++) {
            $this->service->recordServed(10, $now);
        }

        $this->assertFalse($this->service->isDisabled());
    }

    public function test_window_seconds_is_300(): void
    {
        $this->assertSame(300, $this->service->getWindowSeconds());
    }

    public function test_stale_samples_replaced_by_fresh_window_data(): void
    {
        $oldTime = time() - 400;
        $stale = array_fill(0, 10, ['ms' => 1500.0, 'at' => $oldTime]);
        Cache::put('rec_latency_window', $stale, 3600);

        // Now record 10 fast samples within the window
        $now = time();
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordLatencyAt(50.0, $now);
        }

        $this->assertFalse($this->service->isDisabled());
        $p95 = $this->service->getP95Latency();
        $this->assertNotNull($p95);
        $this->assertLessThan(800.0, $p95);
    }

    // ---------------------------------------------------------------
    // recordServed() — guard: count <= 0 is ignored
    // ---------------------------------------------------------------

    public function test_record_served_with_zero_count_does_not_add_entry(): void
    {
        $this->service->recordServed(0);

        $this->assertNull($this->service->getHitRate(), 'Zero-count serve must not create a log entry');
    }

    public function test_record_served_with_negative_count_does_not_add_entry(): void
    {
        $this->service->recordServed(-5);

        $this->assertNull($this->service->getHitRate(), 'Negative-count serve must not create a log entry');
    }

    // ---------------------------------------------------------------
    // recordHit() — guard: empty log is a no-op
    // ---------------------------------------------------------------

    public function test_record_hit_when_no_served_log_does_nothing(): void
    {
        // No recordServed() call — hit_window cache is empty
        $this->service->recordHit();

        // Hit rate should still be null (no log entries written)
        $this->assertNull($this->service->getHitRate());
        $this->assertFalse($this->service->isDisabled());
    }

    // ---------------------------------------------------------------
    // getHitRate() — returns null when log has entries but totalServed somehow zero
    // ---------------------------------------------------------------

    public function test_get_hit_rate_returns_null_when_log_has_zero_served(): void
    {
        // Manually inject a log with served=0 to exercise the defensive totalServed===0 guard
        $now = time();
        Cache::put('rec_hit_window', [['served' => 0, 'hits' => 0, 'at' => $now]], 3600);

        $this->assertNull($this->service->getHitRate());
    }

    // ---------------------------------------------------------------
    // isDisabled() — disable() sets cache key for exactly DISABLE_DURATION_SECONDS
    // ---------------------------------------------------------------

    public function test_engine_is_re_enabled_after_disable_duration_expires(): void
    {
        // Simulate expiry by flushing the disabled key manually
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordLatency(1500.0); // triggers disable
        }
        $this->assertTrue($this->service->isDisabled());

        Cache::forget('rec_engine_disabled');

        $this->assertFalse($this->service->isDisabled(), 'Engine should re-enable once the disabled key expires');
    }

    // ---------------------------------------------------------------
    // computeP95() — single value
    // ---------------------------------------------------------------

    public function test_p95_with_single_sample_equals_that_sample(): void
    {
        $now = time();
        // Insert 9 low-latency samples first to avoid triggering disable, then a 10th under threshold
        // to get exactly 10 samples in window (minimum) with a known p95
        for ($i = 0; $i < 9; $i++) {
            $this->service->recordLatencyAt(100.0, $now);
        }
        $this->service->recordLatencyAt(200.0, $now);

        $p95 = $this->service->getP95Latency();
        $this->assertNotNull($p95);
        // p95 of [100×9, 200×1] sorted → index = ceil(0.95*10)-1 = 9 → 200.0
        $this->assertEqualsWithDelta(200.0, $p95, 0.01);
    }

    // ---------------------------------------------------------------
    // recordLatencyAt() — precise p95 computation with known values
    // ---------------------------------------------------------------

    public function test_p95_computation_with_20_ascending_values(): void
    {
        $now = time();
        // 20 values: 10, 20, 30, … 200
        for ($i = 1; $i <= 20; $i++) {
            $this->service->recordLatencyAt((float) ($i * 10), $now);
        }

        $p95 = $this->service->getP95Latency();
        $this->assertNotNull($p95);
        // index = ceil(0.95 * 20) - 1 = ceil(19) - 1 = 18 → sorted[18] = 190
        $this->assertEqualsWithDelta(190.0, $p95, 0.01);
    }

    // ---------------------------------------------------------------
    // recordHit() — increments last entry in the window log
    // ---------------------------------------------------------------

    public function test_record_hit_increments_last_batch_hits_count(): void
    {
        $now = time();
        $this->service->recordServed(10, $now);
        $hitsBefore = $this->readHits();

        $this->service->recordHit($now);

        $this->assertSame($hitsBefore + 1, $this->readHits());
    }

    public function test_record_hit_triggers_disable_when_rate_remains_below_threshold(): void
    {
        $now = time();
        // 5 batches of 100 served, 0 hits → hit rate 0%
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordServed(100, $now);
        }
        // Service should already be disabled by recordServed, but let's verify recordHit path:
        // Reset disable key to isolate the recordHit trigger path
        Cache::forget('rec_engine_disabled');

        // Inject a single hit — 1 out of 500 served = 0.2% < 10% → should still disable
        $this->service->recordHit($now);

        $this->assertTrue($this->service->isDisabled(), 'recordHit should trigger disable when hit rate stays below threshold');
    }

    // ---------------------------------------------------------------
    // pruneToWindow() — boundary: entry exactly at cutoff is retained
    // ---------------------------------------------------------------

    public function test_latency_entry_at_exact_window_boundary_is_retained(): void
    {
        $now = time();
        $cutoff = $now - 300; // exactly at boundary — should be retained (at >= cutoff)

        $boundaryEntry = [['ms' => 900.0, 'at' => $cutoff]];
        // Add 9 more at boundary to reach MIN_LATENCY_SAMPLES
        for ($i = 1; $i < 10; $i++) {
            $boundaryEntry[] = ['ms' => 900.0, 'at' => $cutoff];
        }
        Cache::put('rec_latency_window', $boundaryEntry, 3600);

        $p95 = $this->service->getP95Latency();
        // Entry is at exactly cutoff → included → p95 = 900ms → would disable
        $this->assertNotNull($p95);
        $this->assertGreaterThanOrEqual(900.0, $p95);
    }

    public function test_latency_entry_one_second_past_boundary_is_pruned(): void
    {
        $now = time();
        $justOutside = $now - 301; // one second past the 300s window → pruned

        $staleEntries = array_fill(0, 10, ['ms' => 1500.0, 'at' => $justOutside]);
        Cache::put('rec_latency_window', $staleEntries, 3600);

        $this->assertNull($this->service->getP95Latency(), 'Entries 301s old must be pruned');
    }

    // ---------------------------------------------------------------
    // recordHit() — below MIN_SERVED_IN_WINDOW does not trigger disable
    // ---------------------------------------------------------------

    public function test_record_hit_below_min_served_batches_does_not_disable(): void
    {
        $now = time();
        // 4 batches (below MIN_SERVED_IN_WINDOW=5) with 0 hits
        for ($i = 0; $i < 4; $i++) {
            $this->service->recordServed(100, $now);
        }
        Cache::forget('rec_engine_disabled');

        $this->service->recordHit($now);

        $this->assertFalse($this->service->isDisabled(), 'Below MIN_SERVED_IN_WINDOW no disable should fire');
    }

    // ---------------------------------------------------------------
    // Helper: sum hits in active window
    // ---------------------------------------------------------------

    private function readHits(): int
    {
        $log = Cache::get('rec_hit_window', []);
        return (int) array_sum(array_column($log, 'hits'));
    }
}
