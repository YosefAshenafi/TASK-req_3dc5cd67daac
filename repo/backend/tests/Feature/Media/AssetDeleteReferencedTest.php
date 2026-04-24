<?php

use App\Models\Asset;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

// No Queue::fake(): delete does not dispatch any post-upload jobs, but we
// still drop the fake so the underlying behaviour of this endpoint is observed
// through real controller → DB side effects, not hidden by unused fakes.
beforeEach(function () {
    Storage::fake('local');
});

test('delete unreferenced asset returns 204 and soft-deletes the row', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create(['uploaded_by' => $admin->id]);

    $response = $this->withToken($token)->deleteJson("/api/assets/{$asset->id}");
    $response->assertStatus(204);

    // Soft delete: row is excluded from default queries but present with trashed.
    expect(Asset::find($asset->id))->toBeNull();
    $trashed = Asset::withTrashed()->find($asset->id);
    expect($trashed)->not->toBeNull();
    expect($trashed->deleted_at)->not->toBeNull();
});

test('delete asset referenced by a single playlist returns 409 with reference_count=1', function () {
    $admin    = User::factory()->admin()->create();
    $token    = $admin->createToken('test')->plainTextToken;
    $asset    = Asset::factory()->create(['uploaded_by' => $admin->id]);
    $playlist = Playlist::factory()->create(['owner_id' => $admin->id]);

    PlaylistItem::create([
        'playlist_id' => $playlist->id,
        'asset_id'    => $asset->id,
        'position'    => 1,
    ]);

    $response = $this->withToken($token)->deleteJson("/api/assets/{$asset->id}");

    $response->assertStatus(409)
        ->assertJsonStructure(['message', 'reference_count'])
        ->assertJsonPath('reference_count', 1);

    expect($response->json('message'))->toBeString()->not->toBeEmpty();

    // Crucially: delete did NOT partially succeed. The asset is still present,
    // not soft-deleted, and the playlist item still points to it.
    expect(Asset::find($asset->id))->not->toBeNull();
    expect(Asset::find($asset->id)->deleted_at)->toBeNull();
    expect(PlaylistItem::where('asset_id', $asset->id)->count())->toBe(1);
});

test('delete asset referenced by multiple playlists reports the correct count', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create(['uploaded_by' => $admin->id]);

    $p1 = Playlist::factory()->create(['owner_id' => $admin->id]);
    $p2 = Playlist::factory()->create(['owner_id' => $admin->id]);
    $p3 = Playlist::factory()->create(['owner_id' => $admin->id]);

    foreach ([$p1, $p2, $p3] as $p) {
        PlaylistItem::create(['playlist_id' => $p->id, 'asset_id' => $asset->id, 'position' => 1]);
    }

    $response = $this->withToken($token)->deleteJson("/api/assets/{$asset->id}");

    $response->assertStatus(409)->assertJsonPath('reference_count', 3);
    expect(Asset::find($asset->id))->not->toBeNull();
});

test('asset referenced only by favorites and play history (no playlists) can still be deleted', function () {
    // Delete is blocked specifically by *playlist* references because playlists
    // are the only place a missing asset would break a user's curated list.
    // Favorites and history are allowed to dangle (or be cleaned by cascade
    // elsewhere). This test locks that semantic.
    $admin = User::factory()->admin()->create();
    $user  = User::factory()->create();
    $token = $admin->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create(['uploaded_by' => $admin->id]);

    Favorite::create(['user_id' => $user->id, 'asset_id' => $asset->id]);
    PlayHistory::create([
        'user_id'   => $user->id,
        'asset_id'  => $asset->id,
        'played_at' => now(),
    ]);

    $response = $this->withToken($token)->deleteJson("/api/assets/{$asset->id}");
    $response->assertStatus(204);

    expect(Asset::find($asset->id))->toBeNull();
    expect(Asset::withTrashed()->find($asset->id)->deleted_at)->not->toBeNull();
});

test('non-admin cannot delete assets', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create();

    $response = $this->withToken($token)->deleteJson("/api/assets/{$asset->id}");
    $response->assertStatus(403);
    expect(Asset::find($asset->id))->not->toBeNull();
});

test('unauthenticated delete is rejected with 401 and leaves the row in place', function () {
    $asset = Asset::factory()->create();

    $response = $this->deleteJson("/api/assets/{$asset->id}");
    $response->assertStatus(401);
    expect(Asset::find($asset->id))->not->toBeNull();
});

test('delete of a non-existent asset returns 404', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->deleteJson('/api/assets/9999999');
    $response->assertStatus(404);
});
