<?php

use App\Models\Asset;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\PlaylistItem;
use App\Models\SearchIndex;
use App\Models\User;
use App\Services\MediaProbe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();
});

function makeMp3ForReplace(): UploadedFile
{
    $content  = "\x49\x44\x33\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'repl') . '.mp3';
    file_put_contents($tempFile, $content);
    return new UploadedFile($tempFile, 'replacement.mp3', 'audio/mpeg', null, true);
}

test('replace remaps all playlist/favorite/history references to new asset', function () {
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
        ->assertJsonStructure(['old_asset_id', 'new_asset_id', 'remapped_playlists', 'remapped_favorites', 'remapped_history']);

    $newAssetId = $response->json('new_asset_id');

    // References should now point to new asset
    expect(PlaylistItem::find($item->id)->asset_id)->toBe($newAssetId);
    expect(Favorite::where('user_id', $user->id)->first()->asset_id)->toBe($newAssetId);
    expect(PlayHistory::where('user_id', $user->id)->first()->asset_id)->toBe($newAssetId);
    expect(SearchIndex::where('asset_id', $newAssetId)->exists())->toBeTrue();

    // Old asset should be soft-deleted
    expect(Asset::withTrashed()->find($oldAsset->id)->deleted_at)->not->toBeNull();
});

test('replace with invalid file returns 422 and old asset is untouched', function () {
    $admin    = User::factory()->admin()->create();
    $oldAsset = Asset::factory()->create(['status' => 'ready']);
    $token    = $admin->createToken('test')->plainTextToken;

    // Send a PNG file declared as MP4 — should fail validation
    $content  = "\x89\x50\x4E\x47\r\n\x1A\n" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'bad') . '.mp4';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'bad.mp4', 'video/mp4', null, true);

    $response = $this->withToken($token)->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'file'  => $file,
        'title' => 'Bad Replacement',
    ]);

    $response->assertStatus(422);

    // Old asset should be untouched
    expect(Asset::find($oldAsset->id))->not->toBeNull();
    expect(Asset::withTrashed()->find($oldAsset->id)->deleted_at)->toBeNull();
});

test('non-admin cannot replace an asset via admin endpoint', function () {
    $user     = User::factory()->create(['role' => 'user']);
    $oldAsset = Asset::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'file' => makeMp3ForReplace(),
    ])->assertStatus(403);
});
