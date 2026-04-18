<?php

use App\Models\Asset;
use App\Models\AssetTag;
use App\Models\Favorite;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistShare;
use App\Models\SearchIndex;
use App\Models\User;
use Illuminate\Support\Str;

test('Asset exposes relations used by API layer', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['uploaded_by' => $user->id]);

    AssetTag::create(['asset_id' => $asset->id, 'tag' => 'demo']);

    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);
    PlaylistItem::create([
        'playlist_id' => $playlist->id,
        'asset_id'    => $asset->id,
        'position'    => 0,
    ]);

    Favorite::create(['user_id' => $user->id, 'asset_id' => $asset->id]);

    $asset->load(['uploader', 'assetTags', 'playlistItems', 'favorites', 'searchIndex']);

    expect($asset->uploader->id)->toBe($user->id);
    expect($asset->assetTags)->toHaveCount(1);
    expect($asset->playlistItems)->toHaveCount(1);
    expect($asset->favorites)->toHaveCount(1);
});

test('SearchIndex belongs to asset', function () {
    $asset = Asset::factory()->create();
    SearchIndex::create([
        'asset_id'         => $asset->id,
        'tokenized_title'  => 'hello',
        'tokenized_body'   => 'world',
        'weight_tsv'       => null,
    ]);

    $row = SearchIndex::where('asset_id', $asset->id)->first();
    expect($row->asset->id)->toBe($asset->id);
});

test('User playlistShares relation is reachable', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $pl = Playlist::factory()->create(['owner_id' => $owner->id]);
    PlaylistShare::create([
        'playlist_id' => $pl->id,
        'code'        => strtoupper(substr(str_replace('-', '', Str::uuid()->toString()), 0, 8)),
        'expires_at'  => now()->addDay(),
        'created_by'  => $admin->id,
    ]);

    $admin->load('playlistShares');
    expect($admin->playlistShares)->toHaveCount(1);
});
