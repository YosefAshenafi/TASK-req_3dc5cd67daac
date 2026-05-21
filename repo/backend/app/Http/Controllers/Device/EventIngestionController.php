<?php

declare(strict_types=1);

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\DeviceReplayAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventIngestionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer'],
            'event_type' => ['required', 'string', 'max:100'],
            'payload' => ['required', 'array'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'sequence' => ['required', 'integer', 'min:0'],
            'replay_audit_id' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');

        if ((int) $data['device_id'] !== $device->id) {
            return response()->json([
                'message' => 'device_id does not match authenticated device.',
                'errors' => ['device_id' => ['Mismatch with authenticated device.']],
            ], 422);
        }

        $replayAuditFk = null;
        if (isset($data['replay_audit_id']) && $data['replay_audit_id'] !== null) {
            $auditRecord = DeviceReplayAudit::where('audit_key', $data['replay_audit_id'])
                ->where('device_id', $device->id)
                ->first();
            if ($auditRecord === null) {
                return response()->json([
                    'message' => 'replay_audit_id does not reference a valid audit record.',
                    'errors' => ['replay_audit_id' => ['Invalid or unknown audit key.']],
                ], 422);
            }
            $replayAuditFk = $auditRecord->id;
        }

        $existing = DeviceEvent::where('device_id', $device->id)
            ->where('idempotency_key', $data['idempotency_key'])
            ->where('received_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 7 DAY)'))
            ->first();

        if ($existing !== null) {
            return response()->json([
                'id' => $existing->id,
                'status' => 'duplicate',
            ], 200);
        }

        $isReplay = isset($data['replay_audit_id']) && $data['replay_audit_id'] !== null;
        $newSeq = (int) $data['sequence'];

        $status = match (true) {
            $isReplay => 'buffered',
            $newSeq < $device->last_sequence => 'late',
            default => 'received',
        };

        $event = null;

        DB::transaction(function () use ($device, $data, $status, $newSeq, $isReplay, $replayAuditFk, &$event): void {
            $event = DeviceEvent::create([
                'device_id' => $device->id,
                'event_type' => $data['event_type'],
                'payload' => $data['payload'],
                'idempotency_key' => $data['idempotency_key'],
                'sequence' => $newSeq,
                'status' => $status,
                'replay_audit_id' => $data['replay_audit_id'] ?? null,
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

        Log::info('device_event_ingested', [
            'device_id' => $device->id,
            'event_id' => $event->id,
            'sequence' => $newSeq,
            'status' => $status,
        ]);

        return response()->json([
            'id' => $event->id,
            'status' => $status,
        ], 201);
    }
}
