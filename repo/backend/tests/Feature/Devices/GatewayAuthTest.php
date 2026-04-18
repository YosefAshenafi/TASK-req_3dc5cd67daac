<?php

use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Support\Str;

function makeGatewayPayload(string $deviceId = 'gw-device-01', int $seqNo = 1): array
{
    return [
        'device_id'   => $deviceId,
        'event_type'  => 'gate.opened',
        'sequence_no' => $seqNo,
        'occurred_at' => now()->toIso8601String(),
        'payload'     => ['lane' => 'E1'],
    ];
}

test('POST /api/gateway/events without token returns 401', function () {
    $iKey = (string) Str::uuid();

    $this->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/gateway/events', makeGatewayPayload())
        ->assertStatus(401);
});

test('POST /api/gateway/events with wrong token returns 401', function () {
    config(['smartpark.gateway.token' => 'correct-secret']);

    $iKey = (string) Str::uuid();

    $this->withHeaders([
        'X-Idempotency-Key' => $iKey,
        'X-Gateway-Token'   => 'wrong-secret',
    ])
    ->postJson('/api/gateway/events', makeGatewayPayload())
    ->assertStatus(401);
});

test('POST /api/gateway/events with correct token returns 201 and persists event', function () {
    config(['smartpark.gateway.token' => 'test-gateway-secret']);

    $iKey = (string) Str::uuid();

    $response = $this->withHeaders([
        'X-Idempotency-Key' => $iKey,
        'X-Gateway-Token'   => 'test-gateway-secret',
    ])
    ->postJson('/api/gateway/events', makeGatewayPayload('gw-persist-01', 1));

    $response->assertStatus(201)
        ->assertJsonPath('status', 'accepted');

    $event = DeviceEvent::where('idempotency_key', $iKey)->first();
    expect($event)->not->toBeNull();
    expect($event->device_id)->toBe('gw-persist-01');
});

test('personal Sanctum token cannot use the gateway-only route', function () {
    $tech  = User::factory()->technician()->create();
    $token = $tech->createToken('personal')->plainTextToken;
    $iKey  = (string) Str::uuid();

    // The gateway route requires X-Gateway-Token, not a Sanctum Bearer token
    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/gateway/events', makeGatewayPayload())
        ->assertStatus(401);
});

test('gateway route still enforces device event validation', function () {
    config(['smartpark.gateway.token' => 'test-gateway-secret']);

    $iKey = (string) Str::uuid();

    $this->withHeaders([
        'X-Idempotency-Key' => $iKey,
        'X-Gateway-Token'   => 'test-gateway-secret',
    ])
    ->postJson('/api/gateway/events', [
        // Missing required fields
        'event_type' => 'gate.opened',
    ])
    ->assertStatus(422);
});
