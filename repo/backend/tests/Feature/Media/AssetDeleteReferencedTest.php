<?php

use App\Models\Asset;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
});

test('delete unreferenced asset returns 204', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create(['uploaded_by' => $admin->id]);

    $this->withToken($token)->deleteJson("/api/assets/{$asset->id}")
        ->assertStatus(204);
});

test('delete asset referenced by playlist returns 409 with playlist_ids', function () {
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
        ->assertJsonStructure(['message', 'reference_count']);
});
