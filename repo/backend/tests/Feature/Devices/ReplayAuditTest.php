<?php

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\ReplayAudit;
use App\Models\User;
use Illuminate\Support\Str;

test('replay creates an audit record and returns full ReplayAudit resource', function () {
    $tech  = User::factory()->technician()->create();
    $token = $tech->createToken('test')->plainTextToken;

    Device::create(['id' => 'gate-replay-01', 'kind' => 'gate', 'last_sequence_no' => 100]);

    $response = $this->withToken($token)->postJson('/api/devices/gate-replay-01/replay', [
        'since_sequence_no' => 1,
        'until_sequence_no' => 100,
        'reason'            => 'LAN outage',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'device_id', 'initiated_by', 'since_sequence_no', 'until_sequence_no', 'reason', 'created_at'])
        ->assertJsonPath('device_id', 'gate-replay-01')
        ->assertJsonPath('since_sequence_no', 1)
        ->assertJsonPath('reason', 'LAN outage');

    $audit = ReplayAudit::where('device_id', 'gate-replay-01')->first();
    expect($audit)->not->toBeNull();
    expect($audit->reason)->toEqual('LAN outage');
});

test('deduplication still applies after replay', function () {
    $tech  = User::factory()->technician()->create();
    $token = $tech->createToken('test')->plainTextToken;

    Device::create(['id' => 'gate-replay-02', 'kind' => 'gate', 'last_sequence_no' => 0]);

    $iKey = (string) Str::uuid();
    $payload = [
        'device_id'   => 'gate-replay-02',
        'event_type'  => 'gate.opened',
        'sequence_no' => 10,
        'occurred_at' => now()->toIso8601String(),
        'payload'     => [],
        'idempotency_key' => $iKey,
    ];

    // Submit event once
    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(201);

    // Submit same event again (like a replay)
    $response = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'duplicate');
});
