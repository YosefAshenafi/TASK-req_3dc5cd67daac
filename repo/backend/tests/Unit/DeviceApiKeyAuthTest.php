<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Device;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceApiKeyAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_missing_authorization_header_returns_401(): void
    {
        $this->postJson('/api/device/events', [])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_non_bearer_authorization_header_returns_401(): void
    {
        $this->withHeaders(['Authorization' => 'Basic dXNlcjpwYXNz'])
            ->postJson('/api/device/events', [])
            ->assertStatus(401);
    }

    public function test_invalid_api_key_returns_401(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer invalid-key-that-does-not-exist'])
            ->postJson('/api/device/events', [])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_valid_api_key_passes_middleware(): void
    {
        $rawKey = 'test-device-api-key-' . uniqid();
        Device::create([
            'name' => 'Test Gate',
            'device_type' => 'gate',
            'api_key_hash' => hash('sha256', $rawKey),
            'last_sequence' => 0,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$rawKey}"])
            ->postJson('/api/device/events', []);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_valid_key_for_different_device_does_not_match_wrong_hash(): void
    {
        $correctKey = 'correct-device-key';
        $wrongKey = 'wrong-device-key';

        Device::create([
            'name' => 'Real Device',
            'device_type' => 'gate',
            'api_key_hash' => hash('sha256', $correctKey),
            'last_sequence' => 0,
        ]);

        $this->withHeaders(['Authorization' => "Bearer {$wrongKey}"])
            ->postJson('/api/device/events', [])
            ->assertStatus(401);
    }
}
