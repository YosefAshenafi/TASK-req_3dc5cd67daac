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

test('ingested event has status accepted in events listing', function () {
    [, $token] = makeDeviceToken();

    $deviceId = 'gate-status-test-01';
    $iKey     = (string) Str::uuid();

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', makeDevicePayload($deviceId, 1, $iKey))
        ->assertStatus(201);

    $response = $this->withToken($token)->getJson("/api/devices/{$deviceId}/events");

    $response->assertStatus(200);
    $items = $response->json('items');
    expect($items)->not->toBeEmpty();
    expect($items[0]['status'])->toBe('accepted');
});

test('out-of-order event has status out_of_order in events listing', function () {
    [, $token] = makeDeviceToken();

    $deviceId = 'gate-oor-status-01';
    Device::create(['id' => $deviceId, 'kind' => 'gate', 'last_sequence_no' => 500]);

    $iKey = (string) Str::uuid();
    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', makeDevicePayload($deviceId, 50, $iKey))
        ->assertStatus(202);

    $response = $this->withToken($token)->getJson("/api/devices/{$deviceId}/events");

    $response->assertStatus(200);
    $items = $response->json('items');
    expect($items)->not->toBeEmpty();
    expect($items[0]['status'])->toBe('out_of_order');
});

test('GET /devices/{id} returns device detail with label field', function () {
    [, $token] = makeDeviceToken();

    Device::create(['id' => 'gate-detail-01', 'kind' => 'gate', 'label' => 'West Entrance', 'last_sequence_no' => 0]);

    $response = $this->withToken($token)->getJson('/api/devices/gate-detail-01');

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'kind', 'label', 'last_sequence_no'])
        ->assertJsonPath('label', 'West Entrance');
});

test('event submitted with X-Buffered-At persists buffered_at and is returned in events list', function () {
    [, $token] = makeDeviceToken();

    $deviceId   = 'gate-buffered-01';
    $iKey       = (string) Str::uuid();
    $bufferedAt = now()->subMinutes(5)->toIso8601String();

    $this->withToken($token)
        ->withHeaders([
            'X-Idempotency-Key' => $iKey,
            'X-Buffered-At'     => $bufferedAt,
            'X-Buffered'        => 'true',
        ])
        ->postJson('/api/devices/events', makeDevicePayload($deviceId, 1, $iKey))
        ->assertStatus(201);

    $event = DeviceEvent::where('idempotency_key', $iKey)->first();
    expect($event)->not->toBeNull();
    expect($event->buffered_at)->not->toBeNull();
    expect($event->buffered_by_gateway)->toBeTrue();

    $response = $this->withToken($token)->getJson("/api/devices/{$deviceId}/events");
    $items    = $response->json('items');
    expect($items[0])->toHaveKey('buffered_at');
    expect($items[0]['buffered_at'])->not->toBeNull();
});

test('duplicate submission creates audit row with status duplicate', function () {
    [, $token] = makeDeviceToken();

    $iKey    = (string) Str::uuid();
    $payload = makeDevicePayload('gate-dup-audit-01', 5, $iKey);

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(201);

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(200)
        ->assertJsonPath('status', 'duplicate');

    // Original event should exist with accepted status
    $original = DeviceEvent::where('idempotency_key', $iKey)->first();
    expect($original)->not->toBeNull();
    expect($original->status)->toBe('accepted');
});
