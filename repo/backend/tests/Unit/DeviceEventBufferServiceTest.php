<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Device;
use App\Models\DeviceEventBuffer;
use App\Services\DeviceEventBufferService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceEventBufferServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeviceEventBufferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->service = new DeviceEventBufferService();
    }

    private function makeDevice(): Device
    {
        return Device::create([
            'name' => 'Unit Test Device',
            'device_type' => 'gate',
            'api_key_hash' => hash('sha256', 'unit-buf-key-' . uniqid()),
            'last_sequence' => 0,
        ]);
    }

    /** @param list<array<string,mixed>> $rows */
    private function bulkInsert(int $deviceId, string $state, int $count): void
    {
        $now = now()->toDateTimeString();
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'device_id' => $deviceId,
                'event_type' => 'gate_open',
                'event_payload' => '{}',
                'idempotency_key' => "{$state}-{$i}",
                'sequence' => $i,
                'replay_audit_id' => null,
                'delivery_state' => $state,
                'attempt_count' => 0,
                'next_retry_at' => null,
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('device_event_buffer')->insert($chunk);
        }
    }

    // ---------------------------------------------------------------
    // Backoff formula
    // ---------------------------------------------------------------

    public function test_backoff_at_attempt_0_equals_base_seconds(): void
    {
        Carbon::setTestNow(Carbon::now());
        $result = $this->service->computeNextRetryAt(0);
        $expected = now()->addSeconds(DeviceEventBufferService::BACKOFF_BASE_SECONDS);
        $this->assertEqualsWithDelta($expected->timestamp, $result->timestamp, 1);
        Carbon::setTestNow(null);
    }

    public function test_backoff_at_attempt_1_doubles_base(): void
    {
        Carbon::setTestNow(Carbon::now());
        $result = $this->service->computeNextRetryAt(1);
        $expected = now()->addSeconds(DeviceEventBufferService::BACKOFF_BASE_SECONDS * 2);
        $this->assertEqualsWithDelta($expected->timestamp, $result->timestamp, 1);
        Carbon::setTestNow(null);
    }

    public function test_backoff_at_attempt_2_quadruples_base(): void
    {
        Carbon::setTestNow(Carbon::now());
        $result = $this->service->computeNextRetryAt(2);
        $expected = now()->addSeconds(DeviceEventBufferService::BACKOFF_BASE_SECONDS * 4);
        $this->assertEqualsWithDelta($expected->timestamp, $result->timestamp, 1);
        Carbon::setTestNow(null);
    }

    public function test_backoff_at_attempt_3_octuples_base(): void
    {
        Carbon::setTestNow(Carbon::now());
        $result = $this->service->computeNextRetryAt(3);
        $expected = now()->addSeconds(DeviceEventBufferService::BACKOFF_BASE_SECONDS * 8);
        $this->assertEqualsWithDelta($expected->timestamp, $result->timestamp, 1);
        Carbon::setTestNow(null);
    }

    public function test_backoff_caps_at_max_seconds_for_large_attempt_count(): void
    {
        // 30 * 2^7 = 3840 > BACKOFF_MAX_SECONDS (3600), so cap applies
        Carbon::setTestNow(Carbon::now());
        $result = $this->service->computeNextRetryAt(7);
        $expected = now()->addSeconds(DeviceEventBufferService::BACKOFF_MAX_SECONDS);
        $this->assertEqualsWithDelta($expected->timestamp, $result->timestamp, 1);
        Carbon::setTestNow(null);
    }

    public function test_backoff_never_exceeds_max_seconds_at_very_high_attempt_count(): void
    {
        for ($i = 7; $i <= 20; $i++) {
            Carbon::setTestNow(Carbon::now());
            $diffSeconds = $this->service->computeNextRetryAt($i)->diffInSeconds(now());
            $this->assertLessThanOrEqual(
                DeviceEventBufferService::BACKOFF_MAX_SECONDS + 1,
                $diffSeconds,
                "Attempt {$i} exceeded BACKOFF_MAX_SECONDS"
            );
            Carbon::setTestNow(null);
        }
    }

    public function test_backoff_is_strictly_increasing_through_cap_boundary(): void
    {
        $prevTimestamp = 0;
        for ($i = 0; $i <= 6; $i++) {
            Carbon::setTestNow(Carbon::now());
            $ts = $this->service->computeNextRetryAt($i)->timestamp;
            $this->assertGreaterThan($prevTimestamp, $ts, "Attempt {$i} not greater than attempt " . ($i - 1));
            $prevTimestamp = $ts;
            Carbon::setTestNow(null);
        }
    }

    // ---------------------------------------------------------------
    // Cap enforcement — below limit
    // ---------------------------------------------------------------

    public function test_enqueue_below_cap_succeeds_without_eviction(): void
    {
        $device = $this->makeDevice();
        $below = DeviceEventBufferService::MAX_PER_DEVICE - 1;

        $this->bulkInsert($device->id, 'pending', $below);

        $entry = $this->service->enqueue($device->id, 'gate_close', [], 'below-cap-new', $below);

        $this->assertSame('pending', $entry->delivery_state);
        $this->assertSame(DeviceEventBufferService::MAX_PER_DEVICE, DeviceEventBuffer::where('device_id', $device->id)->count());
    }

    // ---------------------------------------------------------------
    // Cap enforcement — at limit: eviction
    // ---------------------------------------------------------------

    public function test_cap_at_limit_evicts_exactly_the_oldest_pending_entry(): void
    {
        $device = $this->makeDevice();

        $this->bulkInsert($device->id, 'pending', DeviceEventBufferService::MAX_PER_DEVICE);

        $evictedId = DeviceEventBuffer::where('device_id', $device->id)->orderBy('id')->value('id');

        $this->service->enqueue($device->id, 'gate_close', [], 'at-cap-new', DeviceEventBufferService::MAX_PER_DEVICE);

        $this->assertDatabaseMissing('device_event_buffer', ['id' => $evictedId]);
        $this->assertDatabaseHas('device_event_buffer', ['idempotency_key' => 'at-cap-new']);
    }

    public function test_cap_active_count_stays_at_max_after_eviction(): void
    {
        $device = $this->makeDevice();

        $this->bulkInsert($device->id, 'pending', DeviceEventBufferService::MAX_PER_DEVICE);

        $this->service->enqueue($device->id, 'gate_close', [], 'cap-count-check', DeviceEventBufferService::MAX_PER_DEVICE);

        $activeCount = DeviceEventBuffer::where('device_id', $device->id)
            ->whereIn('delivery_state', ['pending', 'sending', 'failed'])
            ->count();

        $this->assertSame(DeviceEventBufferService::MAX_PER_DEVICE, $activeCount);
    }

    // ---------------------------------------------------------------
    // Cap enforcement — overflow exception
    // ---------------------------------------------------------------

    public function test_cap_throws_overflow_exception_when_all_entries_are_sending(): void
    {
        $device = $this->makeDevice();

        $this->bulkInsert($device->id, 'sending', DeviceEventBufferService::MAX_PER_DEVICE);

        $this->expectException(\OverflowException::class);
        $this->service->enqueue($device->id, 'gate_open', [], 'overflow-sending', DeviceEventBufferService::MAX_PER_DEVICE);
    }

    public function test_cap_throws_overflow_exception_when_all_entries_are_failed(): void
    {
        $device = $this->makeDevice();

        $this->bulkInsert($device->id, 'failed', DeviceEventBufferService::MAX_PER_DEVICE);

        $this->expectException(\OverflowException::class);
        $this->service->enqueue($device->id, 'gate_open', [], 'overflow-failed', DeviceEventBufferService::MAX_PER_DEVICE);
    }

    public function test_cap_evicts_pending_in_preference_to_failed_when_mixed(): void
    {
        $device = $this->makeDevice();
        $half = (int) (DeviceEventBufferService::MAX_PER_DEVICE / 2);
        $now = now()->toDateTimeString();

        // Insert half failed entries, then half pending entries (pending have higher IDs)
        $this->bulkInsert($device->id, 'failed', $half);
        $this->bulkInsert($device->id, 'pending', $half);

        $oldestPendingId = DeviceEventBuffer::where('device_id', $device->id)
            ->where('delivery_state', 'pending')
            ->orderBy('id')
            ->value('id');

        $this->service->enqueue($device->id, 'gate_close', [], 'mixed-new', DeviceEventBufferService::MAX_PER_DEVICE);

        // The oldest pending entry was evicted (not the failed ones)
        $this->assertDatabaseMissing('device_event_buffer', ['id' => $oldestPendingId]);
    }

    // ---------------------------------------------------------------
    // Cap is per-device (other devices unaffected)
    // ---------------------------------------------------------------

    public function test_cap_is_enforced_per_device_independently(): void
    {
        $deviceA = $this->makeDevice();
        $deviceB = $this->makeDevice();

        $this->bulkInsert($deviceA->id, 'pending', DeviceEventBufferService::MAX_PER_DEVICE);

        // Device B is unaffected — enqueue should succeed without eviction
        $entry = $this->service->enqueue($deviceB->id, 'gate_open', [], 'device-b-001', 1);
        $this->assertSame('pending', $entry->delivery_state);
    }
}
