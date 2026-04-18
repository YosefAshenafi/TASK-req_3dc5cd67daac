<?php

use App\Models\Asset;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;

// Non-admin users must not be able to favorite, play, or add playlist items that reference
// assets still under admin review (status != 'ready'). The asset-list, favorites,
// playlist-show, and play-history endpoints must scrub non-ready asset metadata.

test('regular user cannot favorite an asset whose status is processing', function () {
    $user   = User::factory()->create();
    $token  = $user->createToken('test')->plainTextToken;
    $asset  = Asset::factory()->create(['status' => 'processing']);

    $this->withToken($token)->putJson("/api/favorites/{$asset->id}")
        ->assertStatus(404)
        ->assertJsonPath('message', 'Asset not found.');

    expect(Favorite::where('user_id', $user->id)->count())->toBe(0);
});

test('admin can favorite an asset whose status is processing', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create(['status' => 'processing']);

    $this->withToken($token)->putJson("/api/favorites/{$asset->id}")
        ->assertStatus(200);
});

test('regular user cannot record a play against a failed asset', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create(['status' => 'failed']);

    $this->withToken($token)->postJson("/api/assets/{$asset->id}/play")
        ->assertStatus(404);

    expect(PlayHistory::where('user_id', $user->id)->count())->toBe(0);
});

test('regular user cannot add a non-ready asset to a playlist', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);
    $asset    = Asset::factory()->create(['status' => 'processing']);

    $this->withToken($token)->postJson("/api/playlists/{$playlist->id}/items", [
        'asset_id' => $asset->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('reason_code', 'asset_not_ready');

    expect(PlaylistItem::where('playlist_id', $playlist->id)->count())->toBe(0);
});

test('regular user adding a non-existent asset id gets 404', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);

    $this->withToken($token)->postJson("/api/playlists/{$playlist->id}/items", [
        'asset_id' => 999999,
    ])
        ->assertStatus(404)
        ->assertJsonPath('reason_code', 'asset_not_found');
});

test('favorites listing scrubs title and mime for non-ready assets', function () {
    // Prior favorite was captured while the asset was ready; it later regressed to
    // processing. A non-admin must not see the title/MIME anymore.
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create([
        'status' => 'processing',
        'title'  => 'Internal-Only Upload',
        'mime'   => 'audio/mpeg',
    ]);
    Favorite::create(['user_id' => $user->id, 'asset_id' => $asset->id]);

    $response = $this->withToken($token)->getJson('/api/favorites');
    $response->assertStatus(200);

    $items = $response->json('items');
    expect($items)->toHaveCount(1);
    expect($items[0]['asset']['id'])->toBe($asset->id);
    expect($items[0]['asset']['title'])->toBeNull();
    expect($items[0]['asset']['mime'])->toBeNull();
    expect($items[0]['asset']['status'])->toBe('unavailable');
});

test('non-admin asset list only returns ready assets', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $ready      = Asset::factory()->create(['status' => 'ready']);
    $processing = Asset::factory()->create(['status' => 'processing']);
    $failed     = Asset::factory()->create(['status' => 'failed']);

    $response = $this->withToken($token)->getJson('/api/assets');
    $response->assertStatus(200);

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids)->toContain($ready->id);
    expect($ids)->not->toContain($processing->id);
    expect($ids)->not->toContain($failed->id);
});

test('admin asset list can filter by status=processing for the review queue', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $ready      = Asset::factory()->create(['status' => 'ready']);
    $processing = Asset::factory()->create(['status' => 'processing']);
    $failed     = Asset::factory()->create(['status' => 'failed']);

    $resp = $this->withToken($token)->getJson('/api/assets?status=processing');
    $resp->assertStatus(200);
    $ids = collect($resp->json('items'))->pluck('id');
    expect($ids)->toContain($processing->id);
    expect($ids)->not->toContain($ready->id);
    expect($ids)->not->toContain($failed->id);
});

test('admin asset list can return all statuses via status=all', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $ready      = Asset::factory()->create(['status' => 'ready']);
    $processing = Asset::factory()->create(['status' => 'processing']);

    $resp = $this->withToken($token)->getJson('/api/assets?status=all');
    $resp->assertStatus(200);
    $ids = collect($resp->json('items'))->pluck('id');
    expect($ids)->toContain($ready->id);
    expect($ids)->toContain($processing->id);
});

test('admin asset list rejects an unknown status filter', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/assets?status=bogus')
        ->assertStatus(422)
        ->assertJsonPath('reason_code', 'invalid_status_filter');
});
