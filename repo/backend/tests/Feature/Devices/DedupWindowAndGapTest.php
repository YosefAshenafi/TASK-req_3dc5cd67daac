<?php

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

// These tests exercise the 7-day dedup window (Prompt contract) and the per-device
// monotonic-counter semantics for forward gaps vs sequence regressions.

function techToken(): string
{
    $tech = User::factory()->technician()->create();
    return $tech->createToken('test')->plainTextToken;
}

test('same idempotency key after 7 days is accepted as a new event', function () {
    $token = techToken();
    $iKey  = (string) Str::uuid();
    $deviceId = 'gate-window-01';

    // Insert a past event directly so we don't depend on Carbon::setTestNow for the
    // occurred_at comparison inside the controller.
    Device::firstOrCreate(['id' => $deviceId], ['kind' => 'gate', 'last_sequence_no' => 1]);
    DeviceEvent::create([
        'device_id'       => $deviceId,
        'event_type'      => 'gate.opened',
        'sequence_no'     => 1,
        'idempotency_key' => $iKey,
        'occurred_at'     => now()->subDays(10),
        'received_at'     => now()->subDays(10),
        'is_out_of_order' => false,
        'status'          => 'accepted',
        'payload_json'    => ['lane' => 'W1'],
    ]);

    // New event with the same idempotency_key but a fresh occurred_at (today) — must be
    // accepted because it falls outside the 7-day dedup window.
    $response = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', [
            'device_id'       => $deviceId,
            'event_type'      => 'gate.opened',
            'sequence_no'     => 2,
            'occurred_at'     => now()->toIso8601String(),
            'idempotency_key' => $iKey,
            'payload'         => ['lane' => 'W1'],
        ]);

    $response->assertStatus(201)->assertJsonPath('status', 'accepted');
    expect(DeviceEvent::where('device_id', $deviceId)->count())->toBe(2);
});

test('same idempotency key within 7 days is rejected as duplicate', function () {
    $token    = techToken();
    $iKey     = (string) Str::uuid();
    $deviceId = 'gate-window-02';

    $payload = [
        'device_id'       => $deviceId,
        'event_type'      => 'gate.opened',
        'sequence_no'     => 1,
        'occurred_at'     => now()->toIso8601String(),
        'idempotency_key' => $iKey,
        'payload'         => ['lane' => 'W1'],
    ];

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(201);

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(200)
        ->assertJsonPath('status', 'duplicate');
});

test('forward sequence gap is flagged as out_of_order', function () {
    $token    = techToken();
    $deviceId = 'gate-gap-01';

    Device::create(['id' => $deviceId, 'kind' => 'gate', 'last_sequence_no' => 1]);

    $iKey = (string) Str::uuid();

    $response = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', [
            'device_id'       => $deviceId,
            'event_type'      => 'gate.opened',
            'sequence_no'     => 3,
            'occurred_at'     => now()->toIso8601String(),
            'idempotency_key' => $iKey,
            'payload'         => [],
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('status', 'out_of_order')
        ->assertJsonPath('expected_next', 2);

    $event = DeviceEvent::where('idempotency_key', $iKey)->first();
    expect($event->is_out_of_order)->toBeTruthy();
    expect($event->status)->toBe('out_of_order');
});

test('sequence regression below last_sequence_no is flagged as out_of_order', function () {
    $token    = techToken();
    $deviceId = 'gate-regress-01';

    Device::create(['id' => $deviceId, 'kind' => 'gate', 'last_sequence_no' => 50]);

    $iKey = (string) Str::uuid();

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', [
            'device_id'       => $deviceId,
            'event_type'      => 'gate.opened',
            'sequence_no'     => 10,
            'occurred_at'     => now()->toIso8601String(),
            'idempotency_key' => $iKey,
            'payload'         => [],
        ])
        ->assertStatus(202)
        ->assertJsonPath('status', 'out_of_order');
});

test('strictly in-order event (last + 1) is accepted as normal', function () {
    $token    = techToken();
    $deviceId = 'gate-normal-01';

    Device::create(['id' => $deviceId, 'kind' => 'gate', 'last_sequence_no' => 7]);

    $iKey = (string) Str::uuid();

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', [
            'device_id'       => $deviceId,
            'event_type'      => 'gate.opened',
            'sequence_no'     => 8,
            'occurred_at'     => now()->toIso8601String(),
            'idempotency_key' => $iKey,
            'payload'         => [],
        ])
        ->assertStatus(201)
        ->assertJsonPath('status', 'accepted');

    expect(Device::find($deviceId)->last_sequence_no)->toBe(8);
});
