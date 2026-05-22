<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TechnicianDevicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_devices_requires_auth(): void
    {
        $this->getJson('/api/technician/devices')->assertStatus(401);
    }

    public function test_devices_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/technician/devices')
            ->assertStatus(403);
    }

    public function test_devices_forbidden_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/technician/devices')
            ->assertStatus(403);
    }

    public function test_devices_returns_200_for_technician(): void
    {
        $tech = User::factory()->technician()->create();

        $response = $this->actingAs($tech)
            ->getJson('/api/technician/devices');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_devices_returns_device_fields(): void
    {
        $tech = User::factory()->technician()->create();

        $device = Device::create([
            'name' => 'Gate Camera A',
            'device_type' => 'camera',
            'api_key_hash' => hash('sha256', 'test-key-xyz'),
            'last_sequence' => 5,
            'last_event_at' => now()->subMinutes(3),
        ]);

        $response = $this->actingAs($tech)
            ->getJson('/api/technician/devices');

        $response->assertStatus(200);
        $found = collect($response->json('data'))->firstWhere('id', $device->id);
        $this->assertNotNull($found);
        $this->assertArrayHasKey('name', $found);
        $this->assertArrayHasKey('device_type', $found);
        $this->assertArrayHasKey('last_sequence', $found);
        $this->assertArrayHasKey('last_event_at', $found);
    }

    public function test_events_requires_auth(): void
    {
        $this->getJson('/api/technician/events')->assertStatus(401);
    }

    public function test_events_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/technician/events')
            ->assertStatus(403);
    }

    public function test_events_returns_200_for_technician(): void
    {
        $tech = User::factory()->technician()->create();

        $response = $this->actingAs($tech)
            ->getJson('/api/technician/events');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['total', 'page', 'per_page']]);
    }

    public function test_events_filter_by_status(): void
    {
        $tech = User::factory()->technician()->create();
        $device = Device::create([
            'name' => 'Test Gate',
            'device_type' => 'gate',
            'api_key_hash' => hash('sha256', 'filter-test-key'),
            'last_sequence' => 0,
        ]);

        DB::table('device_events')->insert([
            'device_id' => $device->id,
            'idempotency_key' => 'idem-tech-test-001',
            'event_type' => 'gate_open',
            'sequence' => 1,
            'status' => 'received',
            'received_at' => now(),
            'payload' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('device_events')->insert([
            'device_id' => $device->id,
            'idempotency_key' => 'idem-tech-test-002',
            'event_type' => 'gate_open',
            'sequence' => 2,
            'status' => 'late',
            'received_at' => now(),
            'payload' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($tech)
            ->getJson('/api/technician/events?status=received');

        $response->assertStatus(200);
        $statuses = collect($response->json('data'))->pluck('status')->unique()->all();
        $this->assertContains('received', $statuses);
        $this->assertNotContains('late', $statuses);
    }

    // ---------------------------------------------------------------
    // Response body assertions
    // ---------------------------------------------------------------

    public function test_devices_response_count_increases_when_device_added(): void
    {
        $tech = User::factory()->technician()->create();

        $before = count($this->actingAs($tech)->getJson('/api/technician/devices')->json('data'));

        Device::create([
            'name'          => 'New Sensor',
            'device_type'   => 'sensor',
            'api_key_hash'  => hash('sha256', 'count-test-key'),
            'last_sequence' => 0,
        ]);

        $after = count($this->actingAs($tech)->getJson('/api/technician/devices')->json('data'));

        $this->assertSame($before + 1, $after);
    }

    public function test_events_response_entry_has_expected_fields(): void
    {
        $tech   = User::factory()->technician()->create();
        $device = Device::create([
            'name'          => 'Field Gate',
            'device_type'   => 'gate',
            'api_key_hash'  => hash('sha256', 'field-gate-key'),
            'last_sequence' => 0,
        ]);

        DB::table('device_events')->insert([
            'device_id'      => $device->id,
            'idempotency_key' => 'idem-tech-test-003',
            'event_type'     => 'gate_open',
            'sequence'       => 10,
            'status'         => 'received',
            'received_at'    => now(),
            'payload'  => '{}',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $response = $this->actingAs($tech)->getJson('/api/technician/events');
        $response->assertStatus(200);

        $entry = collect($response->json('data'))->first();
        $this->assertNotNull($entry);
        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('device_id', $entry);
        $this->assertArrayHasKey('event_type', $entry);
        $this->assertArrayHasKey('status', $entry);
        $this->assertArrayHasKey('received_at', $entry);
    }

    public function test_events_meta_total_reflects_event_count(): void
    {
        $tech   = User::factory()->technician()->create();
        $device = Device::create([
            'name'          => 'Meta Gate',
            'device_type'   => 'gate',
            'api_key_hash'  => hash('sha256', 'meta-total-key'),
            'last_sequence' => 0,
        ]);

        $initialTotal = $this->actingAs($tech)
            ->getJson('/api/technician/events')
            ->json('meta.total');

        DB::table('device_events')->insert([
            'device_id'     => $device->id,
            'idempotency_key' => 'idem-tech-test-004',
            'event_type'    => 'gate_close',
            'sequence'      => 1,
            'status'        => 'received',
            'received_at'   => now(),
            'payload' => '{}',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $newTotal = $this->actingAs($tech)
            ->getJson('/api/technician/events')
            ->json('meta.total');

        $this->assertSame($initialTotal + 1, $newTotal);
    }

    public function test_events_response_entry_status_is_valid_enum_value(): void
    {
        $tech   = User::factory()->technician()->create();
        $device = Device::create([
            'name'          => 'Enum Gate',
            'device_type'   => 'gate',
            'api_key_hash'  => hash('sha256', 'enum-status-key'),
            'last_sequence' => 0,
        ]);

        DB::table('device_events')->insert([
            'device_id'     => $device->id,
            'idempotency_key' => 'idem-tech-test-005',
            'event_type'    => 'gate_open',
            'sequence'      => 2,
            'status'        => 'buffered',
            'received_at'   => now(),
            'payload' => '{}',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->actingAs($tech)->getJson('/api/technician/events');
        $statuses = collect($response->json('data'))->pluck('status')->all();
        $validStatuses = ['received', 'late', 'buffered', 'duplicate'];
        foreach ($statuses as $status) {
            $this->assertContains($status, $validStatuses, "Unexpected status: $status");
        }
    }
}
