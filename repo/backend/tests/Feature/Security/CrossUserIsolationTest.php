<?php

use App\Models\Asset;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\PlaylistShare;
use App\Models\User;

test('user A cannot see user B favorites via GET /api/favorites', function () {
    $userA  = User::factory()->create();
    $userB  = User::factory()->create();
    $asset  = Asset::factory()->create();
    $tokenA = $userA->createToken('test')->plainTextToken;

    Favorite::create(['user_id' => $userB->id, 'asset_id' => $asset->id]);

    $response = $this->withToken($tokenA)->getJson('/api/favorites');
    $response->assertStatus(200);

    $assetIds = collect($response->json('items'))->pluck('asset_id');
    expect($assetIds)->not->toContain($asset->id);
});

test('user A cannot see user B play history via GET /api/history', function () {
    $userA  = User::factory()->create();
    $userB  = User::factory()->create();
    $asset  = Asset::factory()->create();
    $tokenA = $userA->createToken('test')->plainTextToken;

    PlayHistory::create(['user_id' => $userB->id, 'asset_id' => $asset->id, 'played_at' => now()]);

    $response = $this->withToken($tokenA)->getJson('/api/history');
    $response->assertStatus(200);

    $assetIds = collect($response->json('items'))->pluck('asset_id');
    expect($assetIds)->not->toContain($asset->id);
});

test('play history is recorded for the authenticated user regardless of payload', function () {
    $userA  = User::factory()->create();
    $asset  = Asset::factory()->create();
    $tokenA = $userA->createToken('test')->plainTextToken;

    // The endpoint derives user_id from auth, not from payload
    $this->withToken($tokenA)->postJson("/api/assets/{$asset->id}/play", [])
        ->assertStatus(202);

    $history = PlayHistory::where('user_id', $userA->id)->first();
    expect($history)->not->toBeNull();
    expect($history->asset_id)->toBe($asset->id);
});

test('user A cannot revoke user B share code', function () {
    $userA     = User::factory()->create();
    $userB     = User::factory()->create();
    $tokenA    = $userA->createToken('test')->plainTextToken;
    $playlist  = Playlist::factory()->create(['owner_id' => $userB->id]);

    $share = PlaylistShare::create([
        'playlist_id' => $playlist->id,
        'code'        => 'TESTAB01',
        'created_by'  => $userB->id,
        'expires_at'  => now()->addDay(),
    ]);

    $this->withToken($tokenA)->deleteJson("/api/playlists/shares/{$share->id}")
        ->assertStatus(404); // not found because owner check scopes the query
});

test('duplicate username returns 422', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    User::factory()->create(['username' => 'duplicate_user']);

    $this->withToken($token)->postJson('/api/users', [
        'username' => 'duplicate_user',
        'password' => 'password123',
        'role'     => 'user',
    ])->assertStatus(422);
});

test('non-admin cannot delete an asset', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $asset = Asset::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->deleteJson("/api/assets/{$asset->id}")
        ->assertStatus(403);
});

test('non-admin cannot replace an asset', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $asset = Asset::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson("/api/admin/assets/{$asset->id}/replace", [])
        ->assertStatus(403);
});
