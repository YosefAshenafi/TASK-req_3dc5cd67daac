<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\DeviceEventBuffer;
use App\Models\DeviceReplayAudit;
use App\Services\DeviceEventBufferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlushBufferedDeviceEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DeviceEventBufferService $bufferService): void
    {
        $entries = $bufferService->getDueEntries();

        foreach ($entries as $entry) {
            $bufferService->markSending($entry);

            try {
                $this->ingestEntry($entry);
                $bufferService->markSent($entry);

                Log::info('buffered_event_flushed', [
                    'buffer_id' => $entry->id,
                    'device_id' => $entry->device_id,
                    'idempotency_key' => $entry->idempotency_key,
                ]);
            } catch (\Throwable $e) {
                Log::warning('buffer_flush_failed', [
                    'buffer_id' => $entry->id,
                    'device_id' => $entry->device_id,
                    'error' => $e->getMessage(),
                ]);
                $bufferService->markFailed($entry, $e->getMessage());
            }
        }
    }

    /**
     * Apply the same idempotency, sequence, and replay semantics as the HTTP
     * ingestion controller, preserving event identity fields unchanged.
     */
    private function ingestEntry(DeviceEventBuffer $entry): void
    {
        $device = Device::findOrFail($entry->device_id);

        // 7-day idempotency window — treat duplicate as a no-op success
        $existing = DeviceEvent::where('device_id', $device->id)
            ->where('idempotency_key', $entry->idempotency_key)
            ->where('received_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 7 DAY)'))
            ->first();

        if ($existing !== null) {
            return;
        }

        $isReplay = $entry->replay_audit_id !== null;
        $newSeq = $entry->sequence;

        $replayAuditFk = null;
        if ($isReplay) {
            $auditRecord = DeviceReplayAudit::where('audit_key', $entry->replay_audit_id)
                ->where('device_id', $device->id)
                ->first();
            if ($auditRecord !== null) {
                $replayAuditFk = $auditRecord->id;
            }
        }

        $status = match (true) {
            $isReplay => 'buffered',
            $newSeq < $device->last_sequence => 'late',
            default => 'received',
        };

        DB::transaction(function () use ($device, $entry, $status, $newSeq, $replayAuditFk): void {
            DeviceEvent::create([
                'device_id' => $device->id,
                'event_type' => $entry->event_type,
                'payload' => $entry->event_payload,
                'idempotency_key' => $entry->idempotency_key,
                'sequence' => $newSeq,
                'status' => $status,
                'replay_audit_id' => $entry->replay_audit_id,
                'replay_audit_fk' => $replayAuditFk,
                'received_at' => now(),
            ]);

            if ($status === 'received' && $newSeq >= $device->last_sequence) {
                $device->update([
                    'last_sequence' => $newSeq,
                    'last_event_at' => now(),
                ]);
            }
        });
    }
}
