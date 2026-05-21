<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsoleController extends Controller
{
    public function devices(Request $request): JsonResponse
    {
        $devices = Device::orderByDesc('last_event_at')->get();

        return response()->json([
            'data' => $devices->map(fn (Device $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'device_type' => $d->device_type,
                'last_sequence' => $d->last_sequence,
                'last_event_at' => $d->last_event_at?->toIso8601String(),
                'created_at' => $d->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $query = DeviceEvent::with('device')->orderByDesc('received_at');

        if ($request->filled('device_id')) {
            $query->where('device_id', (int) $request->input('device_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $events = $query->paginate(50);

        return response()->json([
            'data' => $events->items() ? collect($events->items())->map(fn (DeviceEvent $e) => [
                'id' => $e->id,
                'device_id' => $e->device_id,
                'device_name' => $e->device?->name,
                'event_type' => $e->event_type,
                'sequence' => $e->sequence,
                'status' => $e->status,
                'replay_audit_id' => $e->replay_audit_id,
                'received_at' => $e->received_at->toIso8601String(),
            ]) : [],
            'meta' => [
                'total' => $events->total(),
                'page' => $events->currentPage(),
                'per_page' => $events->perPage(),
            ],
        ]);
    }
}
