<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeviceEventBuffer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use OverflowException;

class DeviceEventBufferService
{
    public const int MAX_PER_DEVICE = 10_000;
    public const int BACKOFF_BASE_SECONDS = 30;
    public const int BACKOFF_MAX_SECONDS = 3_600;

    /**
     * Enqueue an outbound event into the durable buffer.
     *
     * When the per-device active count is at MAX_PER_DEVICE, the oldest pending
     * entry is evicted to make room (FIFO eviction). If no pending entry exists
     * to evict (all entries are in sending/failed state) an OverflowException is
     * thrown so the caller can decide how to handle the back-pressure.
     */
    public function enqueue(
        int $deviceId,
        string $eventType,
        array $payload,
        string $idempotencyKey,
        int $sequence,
        ?string $replayAuditId = null,
    ): DeviceEventBuffer {
        $activeCount = DeviceEventBuffer::where('device_id', $deviceId)
            ->whereIn('delivery_state', ['pending', 'sending', 'failed'])
            ->count();

        if ($activeCount >= self::MAX_PER_DEVICE) {
            $oldest = DeviceEventBuffer::where('device_id', $deviceId)
                ->where('delivery_state', 'pending')
                ->orderBy('id')
                ->first();

            if ($oldest === null) {
                throw new OverflowException(
                    "Device {$deviceId} buffer is full ({$activeCount} active entries) with no pending entries available for eviction."
                );
            }

            $oldest->delete();
        }

        return DeviceEventBuffer::create([
            'device_id' => $deviceId,
            'event_type' => $eventType,
            'event_payload' => $payload,
            'idempotency_key' => $idempotencyKey,
            'sequence' => $sequence,
            'replay_audit_id' => $replayAuditId,
            'delivery_state' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => now(),
        ]);
    }

    /**
     * Compute the next retry timestamp using capped exponential backoff.
     *
     * delay = min(BACKOFF_BASE_SECONDS * 2^attemptCount, BACKOFF_MAX_SECONDS)
     */
    public function computeNextRetryAt(int $attemptCount): Carbon
    {
        $seconds = (int) min(
            self::BACKOFF_BASE_SECONDS * (2 ** $attemptCount),
            self::BACKOFF_MAX_SECONDS
        );

        return now()->addSeconds($seconds);
    }

    /**
     * Return pending/failed entries whose next_retry_at is due, ordered for
     * fair per-device FIFO delivery.
     */
    public function getDueEntries(int $limit = 100): Collection
    {
        return DeviceEventBuffer::whereIn('delivery_state', ['pending', 'failed'])
            ->where(function ($query): void {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('device_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function markSending(DeviceEventBuffer $entry): void
    {
        $entry->update(['delivery_state' => 'sending']);
    }

    public function markSent(DeviceEventBuffer $entry): void
    {
        $entry->update(['delivery_state' => 'sent']);
    }

    public function markFailed(DeviceEventBuffer $entry, string $error): void
    {
        $newAttemptCount = $entry->attempt_count + 1;

        $entry->update([
            'delivery_state' => 'failed',
            'attempt_count' => $newAttemptCount,
            'last_error' => $error,
            'next_retry_at' => $this->computeNextRetryAt($newAttemptCount),
        ]);
    }
}
