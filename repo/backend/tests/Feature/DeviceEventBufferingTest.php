<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\FlushBufferedDeviceEventsJob;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\DeviceEventBuffer;
use App\Services\DeviceEventBufferService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceEventBufferingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function makeDevice(int $lastSequence = 0): Device
    {
        return Device::create([
            'name' => 'Buffer Test Device',
            'device_type' => 'gate',
            'api_key_hash' => hash('sha256', 'buffer-test-key-' . uniqid()),
            'last_sequence' => $lastSequence,
        ]);
    }

    /** @param list<array<string,mixed>> $rows */
    private function bulkInsertBufferRows(array $rows): void
    {
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('device_event_buffer')->insert($chunk);
        }
    }

    // ---------------------------------------------------------------
    // Buffering path
    // ---------------------------------------------------------------

    public function test_enqueue_stores_event_in_buffer_with_pending_state(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);

        $entry = $service->enqueue(
            deviceId: $device->id,
            eventType: 'gate_open',
            payload: ['door' => 1],
            idempotencyKey: 'idem-buf-001',
            sequence: 1,
        );

        $this->assertSame('pending', $entry->delivery_state);
        $this->assertSame(0, $entry->attempt_count);
        $this->assertDatabaseHas('device_event_buffer', [
            'device_id' => $device->id,
            'event_type' => 'gate_open',
            'idempotency_key' => 'idem-buf-001',
            'sequence' => 1,
            'delivery_state' => 'pending',
        ]);
    }

    public function test_enqueue_stores_replay_audit_id_when_supplied(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);

        $entry = $service->enqueue(
            deviceId: $device->id,
            eventType: 'gate_open',
            payload: [],
            idempotencyKey: 'idem-replay-buf',
            sequence: 1,
            replayAuditId: 'AUDIT-999',
        );

        $this->assertSame('AUDIT-999', $entry->replay_audit_id);
        $this->assertDatabaseHas('device_event_buffer', [
            'idempotency_key' => 'idem-replay-buf',
            'replay_audit_id' => 'AUDIT-999',
        ]);
    }

    // ---------------------------------------------------------------
    // Per-device cap (10,000)
    // ---------------------------------------------------------------

    public function test_cap_evicts_oldest_pending_entry_when_at_limit(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);
        $now = now()->toDateTimeString();

        $rows = [];
        for ($i = 0; $i < DeviceEventBufferService::MAX_PER_DEVICE; $i++) {
            $rows[] = [
                'device_id' => $device->id,
                'event_type' => 'gate_open',
                'event_payload' => '{}',
                'idempotency_key' => 'cap-' . $i,
                'sequence' => $i,
                'replay_audit_id' => null,
                'delivery_state' => 'pending',
                'attempt_count' => 0,
                'next_retry_at' => null,
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->bulkInsertBufferRows($rows);

        $evictedId = DeviceEventBuffer::where('device_id', $device->id)->orderBy('id')->value('id');

        $service->enqueue($device->id, 'gate_close', [], 'cap-new-001', DeviceEventBufferService::MAX_PER_DEVICE);

        $this->assertDatabaseMissing('device_event_buffer', ['id' => $evictedId]);
        $this->assertDatabaseHas('device_event_buffer', ['idempotency_key' => 'cap-new-001']);

        $activeCount = DeviceEventBuffer::where('device_id', $device->id)
            ->whereIn('delivery_state', ['pending', 'sending', 'failed'])
            ->count();
        $this->assertSame(DeviceEventBufferService::MAX_PER_DEVICE, $activeCount);
    }

    public function test_cap_throws_overflow_exception_when_no_pending_entries_can_be_evicted(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);
        $now = now()->toDateTimeString();

        $rows = [];
        for ($i = 0; $i < DeviceEventBufferService::MAX_PER_DEVICE; $i++) {
            $rows[] = [
                'device_id' => $device->id,
                'event_type' => 'gate_open',
                'event_payload' => '{}',
                'idempotency_key' => 'sending-' . $i,
                'sequence' => $i,
                'replay_audit_id' => null,
                'delivery_state' => 'sending',
                'attempt_count' => 0,
                'next_retry_at' => null,
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->bulkInsertBufferRows($rows);

        $this->expectException(\OverflowException::class);
        $service->enqueue($device->id, 'gate_close', [], 'refuse-001', DeviceEventBufferService::MAX_PER_DEVICE);
    }

    public function test_sent_entries_do_not_count_toward_cap(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);
        $now = now()->toDateTimeString();

        $rows = [];
        for ($i = 0; $i < DeviceEventBufferService::MAX_PER_DEVICE; $i++) {
            $rows[] = [
                'device_id' => $device->id,
                'event_type' => 'gate_open',
                'event_payload' => '{}',
                'idempotency_key' => 'sent-' . $i,
                'sequence' => $i,
                'replay_audit_id' => null,
                'delivery_state' => 'sent',
                'attempt_count' => 0,
                'next_retry_at' => null,
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->bulkInsertBufferRows($rows);

        $entry = $service->enqueue($device->id, 'gate_open', [], 'after-sent-001', DeviceEventBufferService::MAX_PER_DEVICE);
        $this->assertSame('pending', $entry->delivery_state);
    }

    // ---------------------------------------------------------------
    // Exponential backoff progression
    // ---------------------------------------------------------------

    public function test_exponential_backoff_increases_next_retry_at_on_repeated_failures(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);

        $entry = $service->enqueue($device->id, 'gate_open', [], 'idem-backoff-001', 1);

        $service->markFailed($entry, 'first failure');
        $entry->refresh();
        $this->assertSame(1, $entry->attempt_count);
        $firstRetryAt = $entry->next_retry_at->timestamp;

        $service->markFailed($entry, 'second failure');
        $entry->refresh();
        $this->assertSame(2, $entry->attempt_count);
        $secondRetryAt = $entry->next_retry_at->timestamp;

        $service->markFailed($entry, 'third failure');
        $entry->refresh();
        $this->assertSame(3, $entry->attempt_count);
        $thirdRetryAt = $entry->next_retry_at->timestamp;

        $this->assertGreaterThan($firstRetryAt, $secondRetryAt);
        $this->assertGreaterThan($secondRetryAt, $thirdRetryAt);
    }

    public function test_mark_failed_stores_last_error_message(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);

        $entry = $service->enqueue($device->id, 'gate_open', [], 'idem-err-001', 1);
        $service->markFailed($entry, 'Connection refused');
        $entry->refresh();

        $this->assertSame('Connection refused', $entry->last_error);
        $this->assertSame('failed', $entry->delivery_state);
        $this->assertTrue($entry->next_retry_at->isFuture());
    }

    // ---------------------------------------------------------------
    // Flush job — successful retransmit
    // ---------------------------------------------------------------

    public function test_flush_job_marks_entry_sent_and_creates_device_event(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);

        $service->enqueue($device->id, 'gate_open', ['door' => 1], 'idem-flush-001', 1);

        FlushBufferedDeviceEventsJob::dispatchSync();

        $this->assertDatabaseHas('device_event_buffer', [
            'idempotency_key' => 'idem-flush-001',
            'delivery_state' => 'sent',
        ]);
        $this->assertDatabaseHas('device_events', [
            'device_id' => $device->id,
            'idempotency_key' => 'idem-flush-001',
        ]);
    }

    // ---------------------------------------------------------------
    // Idempotency / replay preservation
    // ---------------------------------------------------------------

    public function test_flush_job_preserves_idempotency_key_and_sequence_unchanged(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);

        $service->enqueue($device->id, 'gate_open', ['x' => 1], 'idem-idem-001', 42);

        FlushBufferedDeviceEventsJob::dispatchSync();

        $event = DeviceEvent::where('idempotency_key', 'idem-idem-001')->first();
        $this->assertNotNull($event);
        $this->assertSame(42, $event->sequence);
        $this->assertSame('idem-idem-001', $event->idempotency_key);
    }

    public function test_flush_job_treats_duplicate_idempotency_key_as_success_without_duplicate_event(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);

        DeviceEvent::create([
            'device_id' => $device->id,
            'event_type' => 'gate_open',
            'payload' => ['x' => 1],
            'idempotency_key' => 'idem-dup-001',
            'sequence' => 1,
            'status' => 'received',
            'received_at' => now(),
        ]);

        $service->enqueue($device->id, 'gate_open', ['x' => 1], 'idem-dup-001', 1);

        FlushBufferedDeviceEventsJob::dispatchSync();

        $this->assertDatabaseHas('device_event_buffer', [
            'idempotency_key' => 'idem-dup-001',
            'delivery_state' => 'sent',
        ]);
        $this->assertSame(1, DeviceEvent::where('idempotency_key', 'idem-dup-001')->count());
    }

    public function test_replay_buffer_entry_creates_device_event_with_buffered_status(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);

        $service->enqueue(
            deviceId: $device->id,
            eventType: 'gate_open',
            payload: ['x' => 1],
            idempotencyKey: 'idem-replay-001',
            sequence: 1,
            replayAuditId: 'AUDIT-XYZ',
        );

        FlushBufferedDeviceEventsJob::dispatchSync();

        $event = DeviceEvent::where('idempotency_key', 'idem-replay-001')->first();
        $this->assertNotNull($event);
        $this->assertSame('buffered', $event->status);
        $this->assertSame('AUDIT-XYZ', $event->replay_audit_id);
    }

    // ---------------------------------------------------------------
    // Flush job — failure handling
    // ---------------------------------------------------------------

    public function test_flush_job_marks_entry_failed_and_increments_attempt_count_when_device_missing(): void
    {
        $device = $this->makeDevice();
        $service = app(DeviceEventBufferService::class);

        $entry = $service->enqueue($device->id, 'gate_open', [], 'idem-fail-001', 1);

        // Remove the device to trigger ModelNotFoundException in ingestEntry.
        // FK checks must stay off during the job: MySQL re-validates FK constraints
        // on any UPDATE to a row that references a now-deleted parent row.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('devices')->where('id', $device->id)->delete();
        FlushBufferedDeviceEventsJob::dispatchSync();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $entry->refresh();
        $this->assertSame('failed', $entry->delivery_state);
        $this->assertSame(1, $entry->attempt_count);
        $this->assertNotNull($entry->last_error);
        $this->assertTrue($entry->next_retry_at->isFuture());
    }

    public function test_flush_job_skips_entries_with_future_next_retry_at(): void
    {
        $device = $this->makeDevice();

        DB::table('device_event_buffer')->insert([
            'device_id' => $device->id,
            'event_type' => 'gate_open',
            'event_payload' => '{}',
            'idempotency_key' => 'idem-future-001',
            'sequence' => 1,
            'replay_audit_id' => null,
            'delivery_state' => 'failed',
            'attempt_count' => 1,
            'next_retry_at' => now()->addHour()->toDateTimeString(),
            'last_error' => 'previous error',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        FlushBufferedDeviceEventsJob::dispatchSync();

        $this->assertDatabaseHas('device_event_buffer', [
            'idempotency_key' => 'idem-future-001',
            'delivery_state' => 'failed',
            'attempt_count' => 1,
        ]);
    }
}
