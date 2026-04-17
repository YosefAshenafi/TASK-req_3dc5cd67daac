<?php

namespace App\Http\Controllers;

use App\Jobs\ReconcileDeviceEvents;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\ReplayAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    /**
     * POST /api/devices/events - Ingest a device event.
     *
     * Required headers: X-Idempotency-Key (UUID)
     */
    public function ingestEvent(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (! $idempotencyKey) {
            return response()->json(['message' => 'X-Idempotency-Key header is required.'], 400);
        }

        $request->validate([
            'device_id'   => ['required', 'string', 'max:64'],
            'event_type'  => ['required', 'string', 'max:100'],
            'sequence_no' => ['required', 'integer', 'min:0'],
            'occurred_at' => ['required', 'date'],
            'payload'     => ['nullable', 'array'],
        ]);

        $occurredAt = \Carbon\Carbon::parse($request->input('occurred_at'));

        // Check if event is too old (> 7 days)
        if ($occurredAt->isBefore(now()->subDays(7))) {
            return response()->json([
                'message' => 'Event is too old and will not be accepted.',
                'status'  => 'too_old',
            ], 410);
        }

        $deviceId   = $request->input('device_id');
        $sequenceNo = (int) $request->input('sequence_no');

        // Ensure device exists (upsert)
        $device = Device::firstOrCreate(
            ['id' => $deviceId],
            [
                'kind'             => $request->input('device_kind', 'unknown'),
                'label'            => $request->input('device_label'),
                'last_sequence_no' => 0,
                'last_seen_at'     => now(),
            ]
        );

        // Try to insert the event; catch duplicate
        try {
            $isOutOfOrder = $sequenceNo <= $device->last_sequence_no;

            $event = DeviceEvent::create([
                'device_id'          => $deviceId,
                'event_type'         => $request->input('event_type'),
                'sequence_no'        => $sequenceNo,
                'idempotency_key'    => $idempotencyKey,
                'occurred_at'        => $occurredAt,
                'received_at'        => now(),
                'is_out_of_order'    => $isOutOfOrder,
                'payload_json'       => $request->input('payload'),
                'status'             => $isOutOfOrder ? 'out_of_order' : 'accepted',
                'buffered_by_gateway' => $request->header('X-Buffered') === 'true',
            ]);

            if ($isOutOfOrder) {
                // Dispatch reconciliation job
                ReconcileDeviceEvents::dispatch($deviceId);

                return response()->json([
                    'status'     => 'out_of_order',
                    'message'    => 'Event accepted but is out of order. Reconciliation dispatched.',
                    'event_id'   => $event->id,
                ], 202);
            }

            // Update device last seen and sequence
            DB::table('devices')
                ->where('id', $deviceId)
                ->update([
                    'last_sequence_no' => $sequenceNo,
                    'last_seen_at'     => now(),
                ]);

            return response()->json([
                'status'   => 'accepted',
                'event_id' => $event->id,
            ], 201);

        } catch (UniqueConstraintViolationException $e) {
            return response()->json([
                'status'  => 'duplicate',
                'message' => 'Event already processed.',
            ], 200);
        } catch (\Throwable $e) {
            Log::error("DeviceController@ingestEvent: {$e->getMessage()}", [
                'device_id'       => $deviceId,
                'idempotency_key' => $idempotencyKey,
            ]);

            // If not a unique violation, check if it's actually a duplicate
            $existing = DeviceEvent::where('device_id', $deviceId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return response()->json([
                    'status'  => 'duplicate',
                    'message' => 'Event already processed.',
                ], 200);
            }

            return response()->json(['message' => 'Failed to ingest event.'], 500);
        }
    }

    /**
     * GET /api/devices - List all devices.
     */
    public function index(Request $request): JsonResponse
    {
        $devices = Device::orderBy('id')->get();

        return response()->json($devices->map(fn ($d) => [
            'id'               => $d->id,
            'kind'             => $d->kind,
            'label'            => $d->label,
            'last_sequence_no' => $d->last_sequence_no,
            'last_seen_at'     => $d->last_seen_at?->toIso8601String(),
            'created_at'       => $d->created_at?->toIso8601String(),
        ]));
    }

    /**
     * GET /api/devices/{id} - Get device detail.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        return response()->json([
            'id'               => $device->id,
            'kind'             => $device->kind,
            'label'            => $device->label,
            'last_sequence_no' => $device->last_sequence_no,
            'last_seen_at'     => $device->last_seen_at?->toIso8601String(),
            'created_at'       => $device->created_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/devices/{id}/events - Audit trail for a device.
     */
    public function events(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $perPage = min((int) $request->input('limit', 50), 200);

        $events = DeviceEvent::where('device_id', $device->id)
            ->orderByDesc('occurred_at')
            ->limit($perPage + 1)
            ->get();

        $hasMore    = $events->count() > $perPage;
        $events     = $events->take($perPage);
        $nextCursor = $hasMore ? (string) $events->last()?->id : null;

        return response()->json([
            'items' => $events->map(fn ($e) => [
                'id'              => $e->id,
                'device_id'       => $e->device_id,
                'idempotency_key' => $e->idempotency_key,
                'sequence_no'     => $e->sequence_no,
                'event_type'      => $e->event_type,
                'status'          => $e->status,
                'is_out_of_order' => (bool) $e->is_out_of_order,
                'occurred_at'     => $e->occurred_at?->toIso8601String(),
                'received_at'     => $e->received_at?->toIso8601String(),
            ]),
            'next_cursor' => $nextCursor,
        ]);
    }

    /**
     * GET /api/devices/{id}/replay/audits - List replay audits for a device.
     */
    public function replayAudits(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);
        $audits = ReplayAudit::where('device_id', $device->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        return response()->json($audits->map(fn ($a) => [
            'id'                => $a->id,
            'device_id'         => $a->device_id,
            'initiated_by'      => $a->initiated_by,
            'since_sequence_no' => $a->since_sequence_no,
            'until_sequence_no' => $a->until_sequence_no,
            'reason'            => $a->reason,
            'created_at'        => $a->created_at?->toIso8601String(),
        ]));
    }

    /**
     * POST /api/devices/{id}/replay - Request event replay for a device.
     */
    public function replay(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $request->validate([
            'since_sequence_no' => ['required', 'integer', 'min:0'],
            'until_sequence_no' => ['nullable', 'integer', 'min:0'],
            'reason'            => ['nullable', 'string', 'max:1000'],
        ]);

        $audit = ReplayAudit::create([
            'device_id'         => $device->id,
            'initiated_by'      => $request->user()->id,
            'since_sequence_no' => $request->input('since_sequence_no'),
            'until_sequence_no' => $request->input('until_sequence_no'),
            'reason'            => $request->input('reason'),
            'created_at'        => now(),
        ]);

        return response()->json([
            'message'   => 'Replay audit created.',
            'audit_id'  => $audit->id,
        ], 202);
    }
}
