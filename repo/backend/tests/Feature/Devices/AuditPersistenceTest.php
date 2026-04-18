<?php

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Support\Str;

function makeTechToken(): array
{
    $tech  = User::factory()->technician()->create();
    $token = $tech->createToken('audit-tests')->plainTextToken;
    return [$tech, $token];
}

function auditPayload(string $deviceId, string $iKey, ?string $occurredAt = null, int $seq = 5): array
{
    return [
        'device_id'       => $deviceId,
        'event_type'      => 'gate.opened',
        'sequence_no'     => $seq,
        'occurred_at'     => $occurredAt ?? now()->toIso8601String(),
        'payload'         => ['lane' => 'E3'],
        'idempotency_key' => $iKey,
    ];
}

test('duplicate submission persists a duplicate audit row alongside the accepted original', function () {
    [, $token] = makeTechToken();

    $iKey     = (string) Str::uuid();
    $deviceId = 'audit-dup-01';
    $payload  = auditPayload($deviceId, $iKey);

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(201);

    $dup = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload);

    $dup->assertStatus(200)
        ->assertJsonPath('status', 'duplicate')
        ->assertJsonStructure(['audit_event_id', 'original_event_id']);

    $rows = DeviceEvent::where('device_id', $deviceId)->orderBy('id')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->status)->toBe('accepted');
    expect($rows[1]->status)->toBe('duplicate');
});

test('too-old submission persists an audit row and is visible in the events listing', function () {
    [, $token] = makeTechToken();

    $iKey     = (string) Str::uuid();
    $deviceId = 'audit-too-old-01';

    $response = $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson(
            '/api/devices/events',
            auditPayload($deviceId, $iKey, now()->subDays(8)->toIso8601String())
        );

    $response->assertStatus(410)
        ->assertJsonPath('status', 'too_old')
        ->assertJsonStructure(['event_id']);

    $audit = DeviceEvent::where('device_id', $deviceId)->first();
    expect($audit)->not->toBeNull();
    expect($audit->status)->toBe('too_old');

    $listing = $this->withToken($token)
        ->getJson("/api/devices/{$deviceId}/events?status=too_old");

    $listing->assertStatus(200);
    $items = $listing->json('items');
    expect($items)->not->toBeEmpty();
    expect($items[0]['status'])->toBe('too_old');
});

test('device console filter returns persisted duplicate audit rows', function () {
    [, $token] = makeTechToken();

    $iKey     = (string) Str::uuid();
    $deviceId = 'audit-dup-visibility-01';
    $payload  = auditPayload($deviceId, $iKey);

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(201);

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(200);

    $listing = $this->withToken($token)
        ->getJson("/api/devices/{$deviceId}/events?status=duplicate");

    $listing->assertStatus(200);
    $items = $listing->json('items');
    expect($items)->toHaveCount(1);
    expect($items[0]['status'])->toBe('duplicate');
});

test('repeat duplicate submissions each get their own audit row and never collide', function () {
    [, $token] = makeTechToken();

    $iKey     = (string) Str::uuid();
    $deviceId = 'audit-dup-repeat-01';
    $payload  = auditPayload($deviceId, $iKey);

    $this->withToken($token)
        ->withHeaders(['X-Idempotency-Key' => $iKey])
        ->postJson('/api/devices/events', $payload)
        ->assertStatus(201);

    // Retry twice more — both must persist as additional duplicate audit rows.
    foreach ([1, 2] as $_) {
        $this->withToken($token)
            ->withHeaders(['X-Idempotency-Key' => $iKey])
            ->postJson('/api/devices/events', $payload)
            ->assertStatus(200)
            ->assertJsonPath('status', 'duplicate');
    }

    $dupCount = DeviceEvent::where('device_id', $deviceId)
        ->where('status', 'duplicate')
        ->count();
    expect($dupCount)->toBe(2);

    $acceptedCount = DeviceEvent::where('device_id', $deviceId)
        ->where('status', 'accepted')
        ->count();
    expect($acceptedCount)->toBe(1);
});
