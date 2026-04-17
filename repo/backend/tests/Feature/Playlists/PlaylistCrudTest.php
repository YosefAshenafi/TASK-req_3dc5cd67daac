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
