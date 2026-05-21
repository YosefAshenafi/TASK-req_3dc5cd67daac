<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Favorite;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_favorites_list_requires_auth(): void
    {
        $this->getJson('/api/favorites')->assertStatus(401);
    }

    public function test_favorites_list_returns_only_owner_favorites(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);

        Favorite::create(['user_id' => $owner->id, 'asset_id' => $asset->id]);
        Favorite::create(['user_id' => $other->id, 'asset_id' => $asset->id]);

        $response = $this->actingAs($owner)->getJson('/api/favorites');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('favorite_id')->all();
        $otherFavoriteId = Favorite::where('user_id', $other->id)->value('id');
        $this->assertNotContains($otherFavoriteId, $ids);
    }

    public function test_add_favorite_requires_auth(): void
    {
        $this->postJson('/api/favorites', ['asset_id' => 1])->assertStatus(401);
    }

    public function test_add_favorite_validation_requires_asset_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/favorites', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['asset_id']]);
    }

    public function test_add_favorite_validates_asset_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/favorites', ['asset_id' => 999999])
            ->assertStatus(422);
    }

    public function test_add_favorite_success_returns_201(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/favorites', ['asset_id' => $asset->id]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['favorite_id']]);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
        ]);
    }

    public function test_add_favorite_duplicate_active_returns_200(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);
        $existing = Favorite::create(['user_id' => $user->id, 'asset_id' => $asset->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/favorites', ['asset_id' => $asset->id]);

        $response->assertStatus(200)
            ->assertJsonPath('data.favorite_id', $existing->id);
    }

    public function test_add_favorite_restores_soft_deleted_and_returns_201(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);
        $existing = Favorite::create(['user_id' => $user->id, 'asset_id' => $asset->id]);
        $existing->delete();

        $response = $this->actingAs($user)
            ->postJson('/api/favorites', ['asset_id' => $asset->id]);

        $response->assertStatus(201)
            ->assertJsonPath('data.favorite_id', $existing->id);

        $this->assertDatabaseHas('favorites', [
            'id' => $existing->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_favorite_requires_auth(): void
    {
        $owner = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);
        $favorite = Favorite::create(['user_id' => $owner->id, 'asset_id' => $asset->id]);

        $this->deleteJson("/api/favorites/{$favorite->id}")->assertStatus(401);
    }

    public function test_delete_favorite_owner_succeeds(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);
        $favorite = Favorite::create(['user_id' => $user->id, 'asset_id' => $asset->id]);

        $this->actingAs($user)
            ->deleteJson("/api/favorites/{$favorite->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('favorites', ['id' => $favorite->id]);
    }

    public function test_delete_favorite_non_owner_gets_403(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);
        $favorite = Favorite::create(['user_id' => $owner->id, 'asset_id' => $asset->id]);

        $this->actingAs($other)
            ->deleteJson("/api/favorites/{$favorite->id}")
            ->assertStatus(403);
    }
}
