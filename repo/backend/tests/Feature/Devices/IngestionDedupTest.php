<?php

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Support\Str;

function makeDeviceToken(): array
{
    $tech  = User::factory()->technician()->create();
    $token = $tech->createToken('gateway')->plainTextToken;
    return [$tech, $token];
}

function makeDevicePayload(string $deviceId, int $seqNo, ?string $iKey = null): array
{
    return [
        'device_id'   => $deviceId,
        'event_type'  => 'gate.opened',
        'sequence_no' => $seqNo,
        'occurred_at' => now()->toIso8601String(),
        'payload'     => ['lane' => 'W1'],
        'idempotency_key' => $iKey ?? (string) Str::uuid(),
    ];
}

test('accepted event returns 201 with status accepted', function () {
    [, $token] = makeDeviceToken();

    $iKey = (string) Str::uuid();
    $response = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', makeDevicePayload('gate-west-01', 100, $iKey));

    $response->assertStatus(201)
        ->assertJsonPath('status', 'accepted');
});

test('duplicate idempotency key returns 200 with status duplicate', function () {
    [, $token] = makeDeviceToken();

    $iKey    = (string) Str::uuid();
    $payload = makeDevicePayload('gate-west-02', 100, $iKey);

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(201);

    $response = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'duplicate');
});

test('event older than 7 days returns 410 too_old', function () {
    [, $token] = makeDeviceToken();

    $iKey = (string) Str::uuid();
    $response = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', [
            'device_id'   => 'gate-east-01',
            'event_type'  => 'gate.opened',
            'sequence_no' => 1,
            'occurred_at' => now()->subDays(8)->toIso8601String(),
            'payload'     => [],
            'idempotency_key' => $iKey,
        ]);

    $response->assertStatus(410)
        ->assertJsonPath('status', 'too_old');
});

test('out-of-order event is flagged correctly', function () {
    [, $token] = makeDeviceToken();

    $deviceId = 'gate-south-01';

    Device::create([
        'id'               => $deviceId,
        'kind'             => 'gate',
        'last_sequence_no' => 200,
    ]);

    $iKey = (string) Str::uuid();
    $response = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', makeDevicePayload($deviceId, 50, $iKey));

    $response->assertStatus(202)
        ->assertJsonPath('status', 'out_of_order');

    $event = DeviceEvent::where('idempotency_key', $iKey)->first();
    expect($event->is_out_of_order)->toBeTruthy();
});

test('device events list is accessible to technician', function () {
    [, $token] = makeDeviceToken();

    Device::create(['id' => 'gate-north-01', 'kind' => 'gate', 'last_sequence_no' => 0]);

    $this->withToken($token)
        ->getJson('/api/devices/gate-north-01/events')
        ->assertStatus(200);
});

test('device roster is accessible to technician', function () {
    [, $token] = makeDeviceToken();

    $this->withToken($token)->getJson('/api/devices')->assertStatus(200);
});
