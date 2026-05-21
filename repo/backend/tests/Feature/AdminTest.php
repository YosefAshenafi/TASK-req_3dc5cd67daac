<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_users_list_requires_auth(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
    }

    public function test_admin_users_list_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_admin_users_list_accessible_by_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_freeze_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/freeze", [
                'duration_hours' => 72,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.account_status', 'frozen');
    }

    public function test_admin_freeze_requires_duration(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/freeze", [])
            ->assertStatus(422);
    }

    public function test_admin_blacklist_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/blacklist");

        $response->assertStatus(200)
            ->assertJsonPath('data.account_status', 'blacklisted');
    }

    public function test_admin_delete_user_soft_deletes(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/users/{$target->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_admin_dashboard_accessible_by_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['stats']]);
    }

    public function test_admin_monitoring_not_accessible_by_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/admin/monitoring')
            ->assertStatus(403);
    }

    public function test_admin_monitoring_accessible_by_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/monitoring')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['health', 'queue', 'recommendations']]);
    }

    public function test_technician_events_forbidden_for_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/api/technician/events')
            ->assertStatus(403);
    }

    public function test_technician_events_accessible_by_technician(): void
    {
        $tech = User::factory()->technician()->create();

        $this->actingAs($tech)
            ->getJson('/api/technician/events')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    // ---------------------------------------------------------------
    // Response body shape assertions
    // ---------------------------------------------------------------

    public function test_admin_users_list_response_includes_required_user_fields(): void
    {
        $admin  = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)->getJson('/api/admin/users');

        $response->assertStatus(200);
        $first = collect($response->json('data'))->firstWhere('id', $target->id);
        $this->assertNotNull($first, 'Created user must appear in the list');
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('role', $first);
        $this->assertArrayHasKey('account_status', $first);
    }

    public function test_admin_users_list_meta_contains_total(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/users');

        $response->assertStatus(200);
        $this->assertArrayHasKey('total', $response->json('meta'));
    }

    public function test_admin_freeze_response_includes_frozen_until(): void
    {
        $admin  = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/freeze", ['duration_hours' => 24]);

        $response->assertStatus(200);
        $frozenUntil = $response->json('data.frozen_until');
        $this->assertNotNull($frozenUntil, 'frozen_until must be set');
        $this->assertStringContainsString('T', $frozenUntil, 'frozen_until must be ISO 8601');
    }

    public function test_admin_blacklist_response_contains_account_status_and_structure(): void
    {
        $admin  = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/blacklist");

        $response->assertStatus(200)
            ->assertJsonPath('data.account_status', 'blacklisted')
            ->assertJsonStructure(['data' => ['id', 'name', 'role', 'account_status']]);
    }

    public function test_admin_delete_returns_empty_body(): void
    {
        $admin  = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/admin/users/{$target->id}");

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
    }

    public function test_admin_dashboard_stats_has_users_assets_plays_devices_keys(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $stats = $response->json('data.stats');
        $this->assertArrayHasKey('users', $stats);
        $this->assertArrayHasKey('assets', $stats);
        $this->assertArrayHasKey('plays', $stats);
        $this->assertArrayHasKey('devices', $stats);
    }

    public function test_admin_dashboard_users_stat_contains_total(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
        $this->assertArrayHasKey('total', $response->json('data.stats.users'));
        $this->assertIsInt($response->json('data.stats.users.total'));
    }

    public function test_admin_freeze_validation_error_body_contains_duration_hours(): void
    {
        $admin  = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/freeze", [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['duration_hours']]);
    }
}
