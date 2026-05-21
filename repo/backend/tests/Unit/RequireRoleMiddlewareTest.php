<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequireRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/admin/monitoring')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_wrong_role_returns_403(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/api/admin/monitoring')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_correct_role_passes_through(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/monitoring')
            ->assertStatus(200);
    }

    public function test_technician_role_blocked_from_admin_route(): void
    {
        $tech = User::factory()->technician()->create();

        $this->actingAs($tech)
            ->getJson('/api/admin/monitoring')
            ->assertStatus(403);
    }

    public function test_technician_role_passes_technician_route(): void
    {
        $tech = User::factory()->technician()->create();

        $this->actingAs($tech)
            ->getJson('/api/technician/devices')
            ->assertStatus(200);
    }

    public function test_admin_role_blocked_from_technician_route(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/technician/devices')
            ->assertStatus(403);
    }
}
