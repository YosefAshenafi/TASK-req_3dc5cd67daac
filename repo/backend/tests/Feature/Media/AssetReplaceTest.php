<?php

use App\Models\Asset;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\PlaylistItem;
use App\Models\SearchIndex;
use App\Models\User;
use App\Services\MediaProbe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// No Queue::fake(): QUEUE_CONNECTION=sync runs dispatched jobs inline so we
// can assert their real side effects (status transition, search_index row)
// instead of just that a class name was pushed to the queue.
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

function makeMp3ForReplace(): UploadedFile
{
    $content  = "\x49\x44\x33\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'repl') . '.mp3';
    file_put_contents($tempFile, $content);
    return new UploadedFile($tempFile, 'replacement.mp3', 'audio/mpeg', null, true);
}

test('replace remaps all playlist/favorite/history references to new asset and runs full pipeline', function () {
    // The ffprobe binary is not available in the test-runner image; bind a
    // stub so we can assert a specific duration end-to-end.
    app()->bind(MediaProbe::class, fn () => new class extends MediaProbe {
        public function getDurationSeconds(string $filePath, string $mime): ?int { return 60; }
    });

    $admin    = User::factory()->admin()->create();
    $user     = User::factory()->create();
    $oldAsset = Asset::factory()->create(['status' => 'ready']);
    $token    = $admin->createToken('test')->plainTextToken;

    // Create references to old asset
    $playlist = \App\Models\Playlist::factory()->create(['owner_id' => $user->id]);
    $item     = PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $oldAsset->id, 'position' => 1]);
    Favorite::create(['user_id' => $user->id, 'asset_id' => $oldAsset->id]);
    PlayHistory::create(['user_id' => $user->id, 'asset_id' => $oldAsset->id, 'played_at' => now()]);
    SearchIndex::updateOrCreate(['asset_id' => $oldAsset->id], ['tokenized_title' => 'old', 'tokenized_body' => '']);

    $response = $this->withToken($token)->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'file'  => makeMp3ForReplace(),
        'title' => 'Replacement Audio',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'old_asset_id',
            'new_asset_id',
            'remapped_playlists',
            'remapped_favorites',
            'remapped_history',
            'remapped_candidates',
        ])
        ->assertJsonPath('old_asset_id', $oldAsset->id)
        ->assertJsonPath('remapped_playlists', 1)
        ->assertJsonPath('remapped_favorites', 1)
        ->assertJsonPath('remapped_history', 1);

    $newAssetId = $response->json('new_asset_id');
    expect($newAssetId)->toBeInt()->not->toBe($oldAsset->id);

    // References should now point to new asset
    expect(PlaylistItem::find($item->id)->asset_id)->toBe($newAssetId);
    expect(Favorite::where('user_id', $user->id)->first()->asset_id)->toBe($newAssetId);
    expect(PlayHistory::where('user_id', $user->id)->first()->asset_id)->toBe($newAssetId);

    // New asset persisted: uses sniffed MIME, carries uploaded_by and a real
    // sha256, and its file was actually written to the (fake) local disk.
    $newAsset = Asset::findOrFail($newAssetId);
    expect($newAsset->mime)->toBe('audio/mpeg');
    expect($newAsset->uploaded_by)->toBe($admin->id);
    expect($newAsset->title)->toBe('Replacement Audio');
    expect($newAsset->duration_seconds)->toBe(60);
    expect(strlen((string) $newAsset->fingerprint_sha256))->toBe(64);
    expect(Storage::disk('local')->exists($newAsset->file_path))->toBeTrue();

    // GenerateThumbnails ran sync for the new audio asset: non-image → status
    // transitions to ready.
    expect($newAsset->status)->toBe('ready');

    // IndexAsset ran sync: search_index was re-tokenized for the new asset id.
    $newIndex = SearchIndex::where('asset_id', $newAssetId)->first();
    expect($newIndex)->not->toBeNull();
    expect($newIndex->tokenized_title)->toBe('replacement audio');

    // Old asset should be soft-deleted
    expect(Asset::withTrashed()->find($oldAsset->id)->deleted_at)->not->toBeNull();
    expect(Asset::find($oldAsset->id))->toBeNull();
});

test('replace with no references returns zero remap counts but still creates new asset', function () {
    $admin    = User::factory()->admin()->create();
    $oldAsset = Asset::factory()->create(['status' => 'ready']);
    $token    = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'file' => makeMp3ForReplace(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('remapped_playlists', 0)
        ->assertJsonPath('remapped_favorites', 0)
        ->assertJsonPath('remapped_history', 0)
        ->assertJsonPath('remapped_candidates', 0);

    // When title is omitted, new asset inherits the old title.
    $newAsset = Asset::findOrFail($response->json('new_asset_id'));
    expect($newAsset->title)->toBe($oldAsset->title);

    // Pipeline still ran for the new asset.
    expect($newAsset->status)->toBe('ready');
    expect(SearchIndex::where('asset_id', $newAsset->id)->exists())->toBeTrue();
});

test('replace with invalid file returns 422, preserves old asset, and leaves no new rows behind', function () {
    $admin    = User::factory()->admin()->create();
    $oldAsset = Asset::factory()->create(['status' => 'ready']);
    $token    = $admin->createToken('test')->plainTextToken;

    $assetCountBefore = Asset::count();

    // Send a PNG file declared as MP4 — should fail validation at the magic-byte check.
    $content  = "\x89\x50\x4E\x47\r\n\x1A\n" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'bad') . '.mp4';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'bad.mp4', 'video/mp4', null, true);

    $response = $this->withToken($token)->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'file'  => $file,
        'title' => 'Bad Replacement',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'reason_code'])
        ->assertJsonPath('reason_code', 'magic_mismatch');
    expect($response->json('message'))->toBeString()->not->toBeEmpty();

    // Old asset must be untouched: present, not soft-deleted, title unchanged.
    $reloaded = Asset::withTrashed()->find($oldAsset->id);
    expect($reloaded)->not->toBeNull();
    expect($reloaded->deleted_at)->toBeNull();
    expect($reloaded->title)->toBe($oldAsset->title);

    // No new asset row was created. Validation stopped the pipeline before any
    // side effects: no SearchIndex row for a new asset.
    expect(Asset::count())->toBe($assetCountBefore);
    expect(SearchIndex::count())->toBe(0);
});

test('replace without a file returns 422 with a validation errors payload', function () {
    $admin    = User::factory()->admin()->create();
    $oldAsset = Asset::factory()->create();
    $token    = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'title' => 'Missing File',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['file']);

    // Still a single asset, still not soft-deleted.
    expect(Asset::count())->toBe(1);
    expect(Asset::withTrashed()->find($oldAsset->id)->deleted_at)->toBeNull();
    expect(SearchIndex::count())->toBe(0);
});

test('replace of a non-existent asset id returns 404 with a JSON body', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/admin/assets/9999999/replace', [
        'file' => makeMp3ForReplace(),
    ]);

    // findOrFail in the controller surfaces as 404 to the HTTP layer.
    $response->assertStatus(404);
    expect($response->json())->toBeArray();
});

test('non-admin cannot replace an asset via admin endpoint', function () {
    $user     = User::factory()->create(['role' => 'user']);
    $oldAsset = Asset::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'file' => makeMp3ForReplace(),
    ]);

    $response->assertStatus(403);
    expect(Asset::count())->toBe(1);
    expect(Asset::withTrashed()->find($oldAsset->id)->deleted_at)->toBeNull();
    // No new asset row sneaked through the auth boundary.
    expect(Asset::where('uploaded_by', $user->id)->count())->toBe(0);
});

test('technician cannot replace an asset via admin endpoint', function () {
    $tech     = User::factory()->technician()->create();
    $oldAsset = Asset::factory()->create();
    $token    = $tech->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'file' => makeMp3ForReplace(),
    ]);

    $response->assertStatus(403);
    expect(Asset::count())->toBe(1);
    expect(Asset::withTrashed()->find($oldAsset->id)->deleted_at)->toBeNull();
});

test('unauthenticated replace is rejected with 401 and leaves the old asset intact', function () {
    $oldAsset = Asset::factory()->create();

    $response = $this->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'file' => makeMp3ForReplace(),
    ]);

    $response->assertStatus(401);
    expect(Asset::withTrashed()->find($oldAsset->id)->deleted_at)->toBeNull();
});
