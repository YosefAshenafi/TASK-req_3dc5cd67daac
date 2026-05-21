<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_asset_list_requires_auth(): void
    {
        $this->getJson('/api/admin/assets')->assertStatus(401);
    }

    public function test_admin_asset_list_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/admin/assets')
            ->assertStatus(403);
    }

    public function test_admin_asset_list_returns_200_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/assets');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['total', 'page']]);
    }

    public function test_admin_asset_list_filters_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $uploader = User::factory()->create();

        Asset::factory()->create(['status' => 'pending', 'uploaded_by' => $uploader->id]);
        Asset::factory()->create(['status' => 'approved', 'uploaded_by' => $uploader->id]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/assets?status=pending');

        $response->assertStatus(200);
        $statuses = collect($response->json('data'))->pluck('status')->unique()->all();
        $this->assertEquals(['pending'], $statuses);
    }

    public function test_admin_approve_requires_auth(): void
    {
        $asset = Asset::factory()->create(['status' => 'pending']);

        $this->patchJson("/api/admin/assets/{$asset->id}/approve")
            ->assertStatus(401);
    }

    public function test_admin_approve_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create(['status' => 'pending', 'uploaded_by' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/api/admin/assets/{$asset->id}/approve")
            ->assertStatus(403);
    }

    public function test_admin_approve_sets_status_to_approved(): void
    {
        $admin = User::factory()->admin()->create();
        $uploader = User::factory()->create();
        $asset = Asset::factory()->create(['status' => 'pending', 'uploaded_by' => $uploader->id]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/assets/{$asset->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'approved']);
    }

    public function test_admin_reject_requires_auth(): void
    {
        $asset = Asset::factory()->create(['status' => 'pending']);

        $this->patchJson("/api/admin/assets/{$asset->id}/reject")
            ->assertStatus(401);
    }

    public function test_admin_reject_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create(['status' => 'pending', 'uploaded_by' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/api/admin/assets/{$asset->id}/reject")
            ->assertStatus(403);
    }

    public function test_admin_reject_sets_status_to_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $uploader = User::factory()->create();
        $asset = Asset::factory()->create(['status' => 'pending', 'uploaded_by' => $uploader->id]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/assets/{$asset->id}/reject");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'rejected']);
    }
}
