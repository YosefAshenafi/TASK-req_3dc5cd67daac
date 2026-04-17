<?php

use App\Models\Asset;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;

test('user can create a playlist', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/playlists', [
        'name' => 'Morning Gate Checks',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('name', 'Morning Gate Checks');
});

test('user can list their playlists', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    Playlist::factory()->count(3)->create(['owner_id' => $user->id]);

    $this->withToken($token)->getJson('/api/playlists')
        ->assertStatus(200)
        ->assertJsonCount(3);
});

test('user can get a playlist with items', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);
    $asset    = Asset::factory()->create();

    PlaylistItem::create([
        'playlist_id' => $playlist->id,
        'asset_id'    => $asset->id,
        'position'    => 1,
    ]);

    $response = $this->withToken($token)->getJson("/api/playlists/{$playlist->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'name', 'items'])
        ->assertJsonCount(1, 'items');
});

test('user can rename a playlist', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);

    $this->withToken($token)->patchJson("/api/playlists/{$playlist->id}", [
        'name' => 'Updated Name',
    ])->assertStatus(200)->assertJsonPath('name', 'Updated Name');
});

test('user can delete their playlist', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);

    $this->withToken($token)->deleteJson("/api/playlists/{$playlist->id}")
        ->assertStatus(204);

    expect(Playlist::find($playlist->id))->toBeNull();
});

test('user cannot access another users playlist', function () {
    $user1    = User::factory()->create();
    $user2    = User::factory()->create();
    $token    = $user1->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user2->id]);

    $this->withToken($token)->getJson("/api/playlists/{$playlist->id}")
        ->assertStatus(404);
});

test('POST /playlists/{id}/items adds an item and returns 201', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);
    $asset    = Asset::factory()->create();

    $response = $this->withToken($token)->postJson("/api/playlists/{$playlist->id}/items", [
        'asset_id' => $asset->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'playlist_id', 'asset_id', 'position'])
        ->assertJsonPath('asset_id', $asset->id);

    expect(PlaylistItem::where('playlist_id', $playlist->id)->count())->toBe(1);
});

test('DELETE /playlists/{id}/items/{itemId} removes item and returns 204', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);
    $asset    = Asset::factory()->create();
    $item     = PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $asset->id, 'position' => 1]);

    $this->withToken($token)->deleteJson("/api/playlists/{$playlist->id}/items/{$item->id}")
        ->assertStatus(204);

    expect(PlaylistItem::find($item->id))->toBeNull();
});

test('PUT /playlists/{id}/items/order reorders items and returns 200', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);
    $asset1   = Asset::factory()->create();
    $asset2   = Asset::factory()->create();
    $item1    = PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $asset1->id, 'position' => 1]);
    $item2    = PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $asset2->id, 'position' => 2]);

    $response = $this->withToken($token)->putJson("/api/playlists/{$playlist->id}/items/order", [
        'items' => [
            ['id' => $item1->id, 'position' => 2],
            ['id' => $item2->id, 'position' => 1],
        ],
    ]);

    $response->assertStatus(200);
    expect(PlaylistItem::find($item1->id)->position)->toBe(2);
    expect(PlaylistItem::find($item2->id)->position)->toBe(1);
});

test('non-owner gets 403 on playlist item endpoints', function () {
    $owner    = User::factory()->create();
    $other    = User::factory()->create();
    $token    = $other->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $owner->id]);
    $asset    = Asset::factory()->create();
    $item     = PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $asset->id, 'position' => 1]);

    $this->withToken($token)->postJson("/api/playlists/{$playlist->id}/items", ['asset_id' => $asset->id])
        ->assertStatus(403);

    $this->withToken($token)->deleteJson("/api/playlists/{$playlist->id}/items/{$item->id}")
        ->assertStatus(403);

    $this->withToken($token)->putJson("/api/playlists/{$playlist->id}/items/order", ['items' => []])
        ->assertStatus(403);
});
