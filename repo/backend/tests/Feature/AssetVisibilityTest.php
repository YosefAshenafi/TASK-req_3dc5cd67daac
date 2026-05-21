<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_approved_asset_visible_to_any_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $asset = Asset::factory()->create([
            'status' => 'approved',
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/assets/{$asset->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $asset->id);
    }

    public function test_pending_asset_visible_to_owner(): void
    {
        $owner = User::factory()->create();

        $asset = Asset::factory()->create([
            'status' => 'pending',
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->getJson("/api/assets/{$asset->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $asset->id);
    }

    public function test_pending_asset_hidden_from_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $asset = Asset::factory()->create([
            'status' => 'pending',
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($other)
            ->getJson("/api/assets/{$asset->id}")
            ->assertStatus(404);
    }

    public function test_rejected_asset_hidden_from_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $asset = Asset::factory()->create([
            'status' => 'rejected',
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($other)
            ->getJson("/api/assets/{$asset->id}")
            ->assertStatus(404);
    }

    public function test_admin_can_see_any_status_asset(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();

        foreach (['pending', 'rejected', 'approved'] as $status) {
            $asset = Asset::factory()->create([
                'status' => $status,
                'uploaded_by' => $owner->id,
            ]);

            $this->actingAs($admin)
                ->getJson("/api/assets/{$asset->id}")
                ->assertStatus(200);
        }
    }
}
