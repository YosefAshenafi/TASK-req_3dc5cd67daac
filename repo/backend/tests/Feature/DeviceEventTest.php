<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\DeviceReplayAudit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceEventTest extends TestCase
{
    use RefreshDatabase;

    private string $rawApiKey = 'smartpark-device-key-abc123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function getDevice(): Device
    {
        return Device::where('api_key_hash', hash('sha256', $this->rawApiKey))->first();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'device_id' => $this->getDevice()->id,
            'event_type' => 'gate_open',
            'payload' => ['vehicle_id' => 'ABC-123', 'timestamp' => now()->toIso8601String()],
            'idempotency_key' => 'key-' . uniqid('', true),
            'sequence' => 1,
        ], $overrides);
    }

    public function test_ingest_requires_bearer_key(): void
    {
        $this->postJson('/api/device/events', $this->validPayload())
            ->assertStatus(401);
    }

    public function test_ingest_rejects_invalid_key(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer invalid-key'])
            ->postJson('/api/device/events', $this->validPayload())
            ->assertStatus(401);
    }

    public function test_ingest_creates_event_with_received_status(): void
    {
        Device::where('api_key_hash', hash('sha256', $this->rawApiKey))->update(['last_sequence' => 0]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $this->validPayload(['sequence' => 1]));

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'status'])
            ->assertJson(['status' => 'received']);
    }

    public function test_ingest_requires_device_id(): void
    {
        $payload = $this->validPayload();
        unset($payload['device_id']);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.device_id', fn ($v) => !empty($v));
    }

    public function test_ingest_rejects_mismatched_device_id(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $this->validPayload(['device_id' => 99999]));

        $response->assertStatus(422)
            ->assertJsonPath('errors.device_id', fn ($v) => !empty($v));
    }

    public function test_ingest_deduplicates_within_7_days(): void
    {
        $key = 'dedup-key-' . uniqid('', true);
        $payload = $this->validPayload(['idempotency_key' => $key, 'sequence' => 5]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $payload)
            ->assertStatus(201);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'duplicate']);
    }

    public function test_ingest_late_event_saved_with_late_status(): void
    {
        $device = $this->getDevice();
        $device->update(['last_sequence' => 100]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $this->validPayload(['sequence' => 50]));

        $response->assertStatus(201)
            ->assertJson(['status' => 'late']);
    }

    public function test_ingest_replay_with_valid_audit_key_saves_buffered(): void
    {
        $device = $this->getDevice();
        $admin = User::factory()->admin()->create();

        $audit = DeviceReplayAudit::create([
            'audit_key' => 'VALIDAUDIT01',
            'device_id' => $device->id,
            'triggered_by' => $admin->id,
            'scope' => 'test-scope',
            'reason' => 'unit test replay',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $this->validPayload([
                'sequence' => 200,
                'replay_audit_id' => $audit->audit_key,
            ]));

        $response->assertStatus(201)
            ->assertJson(['status' => 'buffered']);
    }

    public function test_ingest_replay_with_invalid_audit_key_returns_422(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $this->validPayload([
                'sequence' => 200,
                'replay_audit_id' => 'NONEXISTENTKEY',
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('errors.replay_audit_id', fn ($v) => !empty($v));
    }

    public function test_ingest_validation_rejects_missing_fields(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', [])
            ->assertStatus(422);
    }

    public function test_admin_can_create_replay_audit(): void
    {
        $device = $this->getDevice();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/device-replays', [
                'device_id' => $device->id,
                'scope' => 'gate events 2026-05-01..2026-05-07',
                'reason' => 'Data reconciliation after outage',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'audit_key', 'device_id', 'triggered_by', 'triggered_at']]);

        $this->assertDatabaseHas('device_replay_audits', ['device_id' => $device->id]);
    }

    public function test_replay_event_stores_relational_fk_to_audit_record(): void
    {
        $device = $this->getDevice();
        $admin = User::factory()->admin()->create();

        $audit = DeviceReplayAudit::create([
            'audit_key' => 'FKTEST0001',
            'device_id' => $device->id,
            'triggered_by' => $admin->id,
            'scope' => 'fk-test',
            'reason' => 'testing relational linkage',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $this->validPayload([
                'sequence' => 500,
                'replay_audit_id' => $audit->audit_key,
            ]));

        $response->assertStatus(201)->assertJson(['status' => 'buffered']);

        $eventId = $response->json('id');
        $this->assertDatabaseHas('device_events', [
            'id' => $eventId,
            'replay_audit_id' => $audit->audit_key,
            'replay_audit_fk' => $audit->id,
        ]);
    }

    public function test_replay_fk_is_null_for_non_replay_events(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $this->validPayload(['sequence' => 10]));

        $response->assertStatus(201)->assertJson(['status' => 'received']);

        $this->assertDatabaseHas('device_events', [
            'id' => $response->json('id'),
            'replay_audit_fk' => null,
            'replay_audit_id' => null,
        ]);
    }

    public function test_replay_fk_references_correct_audit_record_id(): void
    {
        $device = $this->getDevice();
        $admin = User::factory()->admin()->create();

        $audit1 = DeviceReplayAudit::create([
            'audit_key' => 'AUDIT00001',
            'device_id' => $device->id,
            'triggered_by' => $admin->id,
        ]);
        $audit2 = DeviceReplayAudit::create([
            'audit_key' => 'AUDIT00002',
            'device_id' => $device->id,
            'triggered_by' => $admin->id,
        ]);

        $r1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $this->validPayload([
                'sequence' => 600,
                'replay_audit_id' => 'AUDIT00001',
            ]));
        $r1->assertStatus(201);

        $r2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->rawApiKey])
            ->postJson('/api/device/events', $this->validPayload([
                'sequence' => 601,
                'replay_audit_id' => 'AUDIT00002',
            ]));
        $r2->assertStatus(201);

        $this->assertDatabaseHas('device_events', [
            'id' => $r1->json('id'),
            'replay_audit_fk' => $audit1->id,
        ]);
        $this->assertDatabaseHas('device_events', [
            'id' => $r2->json('id'),
            'replay_audit_fk' => $audit2->id,
        ]);
    }
}
