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
     * Required headers: X-Idempotency-Key (UUID).
     *
     * Dedup is scoped to the last 7 days: the same (device_id, idempotency_key) reappearing
     * after the 7-day window is allowed as a new event. Sequence handling flags both
     * regressions (seq <= last_sequence_no) and forward gaps (seq > last_sequence_no + 1)
     * as `out_of_order`; the reconciliation job advances `last_sequence_no` to the highest
     * contiguous value once the missing event(s) arrive.
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
        $windowStart = now()->subDays(7);
        $deviceId   = $request->input('device_id');
        $sequenceNo = (int) $request->input('sequence_no');

        $bufferedAt = null;
        if ($request->header('X-Buffered-At')) {
            try {
                $bufferedAt = \Carbon\Carbon::parse($request->header('X-Buffered-At'));
            } catch (\Throwable) {
                $bufferedAt = null;
            }
        }

        // Too-old: occurred_at older than the 7-day acceptance window.
        // Persist the attempt as an audit row so technicians can review rejected inflows
        // instead of silently dropping them.
        if ($occurredAt->isBefore($windowStart)) {
            Device::firstOrCreate(
                ['id' => $deviceId],
                [
                    'kind'             => $request->input('device_kind', 'unknown'),
                    'label'            => $request->input('device_label'),
                    'last_sequence_no' => 0,
                    'last_seen_at'     => now(),
                ]
            );

            $auditEvent = DeviceEvent::create([
                'device_id'           => $deviceId,
                'event_type'          => $request->input('event_type'),
                'sequence_no'         => $sequenceNo,
                'idempotency_key'     => $idempotencyKey,
                'occurred_at'         => $occurredAt,
                'received_at'         => now(),
                'is_out_of_order'     => false,
                'payload_json'        => $request->input('payload'),
                'status'              => 'too_old',
                'buffered_by_gateway' => $request->header('X-Buffered') === 'true',
                'buffered_at'         => $bufferedAt,
            ]);

            return response()->json([
                'message'  => 'Event is too old and will not be accepted.',
                'status'   => 'too_old',
                'event_id' => $auditEvent->id,
            ], 410);
        }

        // Dedup: only within the 7-day window. After the window expires the same
        // idempotency_key may be reused (replays without double-applying side effects).
        // We only treat `accepted`/`out_of_order` originals as duplicate sources — audit
        // rows (status IN ('duplicate','too_old')) must not cascade into further matches.
        $windowDuplicate = DeviceEvent::where('device_id', $deviceId)
            ->where('idempotency_key', $idempotencyKey)
            ->where('occurred_at', '>=', $windowStart)
            ->whereIn('status', ['accepted', 'out_of_order'])
            ->first();

        if ($windowDuplicate) {
            // Persist the duplicate attempt as an audit row so the device console can show
            // every retry the gateway (or a misbehaving device) sent after the original.
            $auditEvent = DeviceEvent::create([
                'device_id'           => $deviceId,
                'event_type'          => $request->input('event_type'),
                'sequence_no'         => $sequenceNo,
                'idempotency_key'     => $idempotencyKey,
                'occurred_at'         => $occurredAt,
                'received_at'         => now(),
                'is_out_of_order'     => false,
                'payload_json'        => $request->input('payload'),
                'status'              => 'duplicate',
                'buffered_by_gateway' => $request->header('X-Buffered') === 'true',
                'buffered_at'         => $bufferedAt,
            ]);

            return response()->json([
                'status'            => 'duplicate',
                'message'           => 'Event already processed.',
                'event_id'          => $windowDuplicate->id,
                'audit_event_id'    => $auditEvent->id,
                'original_event_id' => $windowDuplicate->id,
            ], 200);
        }

        // Ensure device exists (upsert).
        $device = Device::firstOrCreate(
            ['id' => $deviceId],
            [
                'kind'             => $request->input('device_kind', 'unknown'),
                'label'            => $request->input('device_label'),
                'last_sequence_no' => 0,
                'last_seen_at'     => now(),
            ]
        );

        // Out-of-order covers both sequence regressions AND forward gaps against the
        // per-device monotonic counter. An event exactly one ahead of last_sequence_no is
        // in-order; anything else is queued for reconciliation.
        //
        // First event ever for a device (row just inserted OR no events yet recorded) is
        // treated as the starting point — we don't know where the device's counter "should"
        // be, so we accept whatever it sends and adopt that value as the new baseline.
        $isFirstEvent      = $device->wasRecentlyCreated
            || ((int) $device->last_sequence_no === 0
                && ! DeviceEvent::where('device_id', $deviceId)->exists());
        $expectedNext      = (int) $device->last_sequence_no + 1;
        $isSequenceRegress = ! $isFirstEvent && $sequenceNo <= (int) $device->last_sequence_no;
        $isForwardGap      = ! $isFirstEvent && $sequenceNo > $expectedNext;
        $isOutOfOrder      = $isSequenceRegress || $isForwardGap;

        try {
            $event = DeviceEvent::create([
                'device_id'           => $deviceId,
                'event_type'          => $request->input('event_type'),
                'sequence_no'         => $sequenceNo,
                'idempotency_key'     => $idempotencyKey,
                'occurred_at'         => $occurredAt,
                'received_at'         => now(),
                'is_out_of_order'     => $isOutOfOrder,
                'payload_json'        => $request->input('payload'),
                'status'              => $isOutOfOrder ? 'out_of_order' : 'accepted',
                'buffered_by_gateway' => $request->header('X-Buffered') === 'true',
                'buffered_at'         => $bufferedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost-race dedup fallback for pre-migration rows that still carry the legacy
            // (device_id, idempotency_key) unique index — treat as duplicate-within-window.
            return response()->json([
                'status'  => 'duplicate',
                'message' => 'Event already processed.',
            ], 200);
        } catch (\Throwable $e) {
            Log::error("DeviceController@ingestEvent: {$e->getMessage()}", [
                'device_id'       => $deviceId,
                'idempotency_key' => $idempotencyKey,
            ]);
            return response()->json(['message' => 'Failed to ingest event.'], 500);
        }

        if ($isOutOfOrder) {
            ReconcileDeviceEvents::dispatch($deviceId);

            $reason = $isSequenceRegress
                ? 'sequence regression'
                : "forward gap (expected {$expectedNext}, got {$sequenceNo})";

            return response()->json([
                'status'        => 'out_of_order',
                'message'       => "Event accepted but is out of order: {$reason}. Reconciliation dispatched.",
                'event_id'      => $event->id,
                'expected_next' => $expectedNext,
            ], 202);
        }

        // Happy path: advance the per-device monotonic counter.
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
     *
     * Query params:
     *   status  - optional filter: accepted | duplicate | out_of_order | too_old
     *   cursor  - paginate by event id (descending)
     *   limit   - results per page (default 50, max 200)
     */
    public function events(Request $request, string $id): JsonResponse
    {
        $device = Device::findOrFail($id);

        $perPage = min((int) $request->input('limit', 50), 200);

        $query = DeviceEvent::where('device_id', $device->id);

        if ($request->filled('status')) {
            $status = $request->input('status');
            $allowed = ['accepted', 'duplicate', 'out_of_order', 'too_old'];
            if (in_array($status, $allowed, true)) {
                $query->where('status', $status);
            }
        }

        // Cursor is the id of the last event on the prior page; we order by id desc,
        // so the next page is strictly lower ids.
        if ($request->filled('cursor')) {
            $query->where('id', '<', (int) $request->input('cursor'));
        }

        $events = $query->orderByDesc('id')
            ->limit($perPage + 1)
            ->get();

        $hasMore    = $events->count() > $perPage;
        $events     = $events->take($perPage);
        $nextCursor = $hasMore ? (string) $events->last()?->id : null;

        return response()->json([
            'items' => $events->map(fn ($e) => [
                'id'                  => $e->id,
                'device_id'           => $e->device_id,
                'idempotency_key'     => $e->idempotency_key,
                'sequence_no'         => $e->sequence_no,
                'event_type'          => $e->event_type,
                'status'              => $e->status,
                'is_out_of_order'     => (bool) $e->is_out_of_order,
                'buffered_by_gateway' => (bool) $e->buffered_by_gateway,
                'buffered_at'         => $e->buffered_at?->toIso8601String(),
                'occurred_at'         => $e->occurred_at?->toIso8601String(),
                'received_at'         => $e->received_at?->toIso8601String(),
                'payload_json'        => $e->payload_json,
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
            'id'                => $audit->id,
            'device_id'         => $audit->device_id,
            'initiated_by'      => $audit->initiated_by,
            'since_sequence_no' => $audit->since_sequence_no,
            'until_sequence_no' => $audit->until_sequence_no,
            'reason'            => $audit->reason,
            'created_at'        => $audit->created_at?->toIso8601String(),
        ], 201);
    }
}
