<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Playlist;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_playlist_list_requires_auth(): void
    {
        $this->getJson('/api/playlists')->assertStatus(401);
    }

    public function test_create_playlist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/playlists', [
                'name' => 'My Test Playlist',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'My Test Playlist');
    }

    public function test_create_playlist_validation_failure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/playlists', [])
            ->assertStatus(422);
    }

    public function test_show_playlist_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->getJson("/api/playlists/{$playlist->id}")
            ->assertStatus(403);
    }

    public function test_delete_playlist_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->deleteJson("/api/playlists/{$playlist->id}")
            ->assertStatus(403);
    }

    public function test_add_item_to_playlist(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $user->id]);
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/playlists/{$playlist->id}/items", [
                'asset_id' => $asset->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['item_id', 'asset_id', 'position']]);
    }

    public function test_redeem_share_code(): void
    {
        $playlist = Playlist::factory()->create(['share_code' => 'TESTCODE']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/playlists/redeem', ['share_code' => 'TESTCODE']);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['playlist']]);
    }

    public function test_redeem_invalid_code_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/playlists/redeem', ['share_code' => 'BADCODE'])
            ->assertStatus(404);
    }

    public function test_update_playlist_success(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $user->id, 'name' => 'Original Name']);

        $response = $this->actingAs($user)
            ->patchJson("/api/playlists/{$playlist->id}", ['name' => 'Updated Name']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('playlists', ['id' => $playlist->id, 'name' => 'Updated Name']);
    }

    public function test_update_playlist_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->patchJson("/api/playlists/{$playlist->id}", ['name' => 'Hijacked'])
            ->assertStatus(403);
    }

    public function test_update_playlist_validates_name_length(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/api/playlists/{$playlist->id}", ['name' => str_repeat('x', 256)])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_remove_item_from_playlist_success(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $user->id]);
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $addResponse = $this->actingAs($user)
            ->postJson("/api/playlists/{$playlist->id}/items", ['asset_id' => $asset->id]);

        $itemId = $addResponse->json('data.item_id');

        $this->actingAs($user)
            ->deleteJson("/api/playlists/{$playlist->id}/items/{$itemId}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('playlist_items', ['id' => $itemId]);
    }

    public function test_remove_item_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $playlist = Playlist::factory()->create(['user_id' => $owner->id]);
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);

        $addResponse = $this->actingAs($owner)
            ->postJson("/api/playlists/{$playlist->id}/items", ['asset_id' => $asset->id]);

        $itemId = $addResponse->json('data.item_id');

        $this->actingAs($other)
            ->deleteJson("/api/playlists/{$playlist->id}/items/{$itemId}")
            ->assertStatus(403);
    }

    public function test_remove_item_returns_404_when_item_belongs_to_different_playlist(): void
    {
        $user = User::factory()->create();
        $playlistA = Playlist::factory()->create(['user_id' => $user->id]);
        $playlistB = Playlist::factory()->create(['user_id' => $user->id]);
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $addResponse = $this->actingAs($user)
            ->postJson("/api/playlists/{$playlistA->id}/items", ['asset_id' => $asset->id]);

        $itemId = $addResponse->json('data.item_id');

        $this->actingAs($user)
            ->deleteJson("/api/playlists/{$playlistB->id}/items/{$itemId}")
            ->assertStatus(404);
    }
}
