<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceReplayAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceReplayAuditController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'scope' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
        ]);

        $auditKey = strtoupper(Str::random(16));

        $audit = DeviceReplayAudit::create([
            'audit_key' => $auditKey,
            'device_id' => $data['device_id'],
            'triggered_by' => $request->user()->id,
            'triggered_at' => now(),
            'scope' => $data['scope'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $audit->id,
                'audit_key' => $audit->audit_key,
                'device_id' => $audit->device_id,
                'triggered_by' => $audit->triggered_by,
                'scope' => $audit->scope,
                'reason' => $audit->reason,
                'triggered_at' => $audit->triggered_at->toIso8601String(),
            ],
        ], 201);
    }
}
