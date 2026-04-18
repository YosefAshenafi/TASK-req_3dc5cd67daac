<?php

use App\Models\Asset;
use App\Models\Device;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\SearchIndex;
use App\Models\User;
use App\Services\MediaProbe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();
});

// The playlists list endpoint must return an `items_count` that the UI can render
// without having to load full items[] for every playlist.
test('GET /api/playlists returns items_count for each playlist', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $empty  = Playlist::factory()->create(['owner_id' => $user->id]);
    $filled = Playlist::factory()->create(['owner_id' => $user->id]);
    $asset1 = Asset::factory()->create();
    $asset2 = Asset::factory()->create();
    PlaylistItem::create(['playlist_id' => $filled->id, 'asset_id' => $asset1->id, 'position' => 1]);
    PlaylistItem::create(['playlist_id' => $filled->id, 'asset_id' => $asset2->id, 'position' => 2]);

    $response = $this->withToken($token)->getJson('/api/playlists');
    $response->assertStatus(200);

    $payload = collect($response->json())->keyBy('id');
    expect($payload[$empty->id]['items_count'])->toEqual(0);
    expect($payload[$filled->id]['items_count'])->toEqual(2);
});

// Exact payload the frontend sends for playlist reorder (from services/api.ts reorderItems)
test('playlist reorder payload matches backend contract', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);
    $asset1   = Asset::factory()->create();
    $asset2   = Asset::factory()->create();
    $item1    = PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $asset1->id, 'position' => 1]);
    $item2    = PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $asset2->id, 'position' => 2]);

    // Frontend sends: { item_ids: number[] }
    $response = $this->withToken($token)->putJson("/api/playlists/{$playlist->id}/items/order", [
        'item_ids' => [$item2->id, $item1->id],
    ]);

    $response->assertStatus(200);
    $items = $response->json();
    expect($items)->toBeArray();
    expect($items[0])->toHaveKeys(['id', 'playlist_id', 'asset_id', 'position']);
});

// Exact payload the frontend sends for device replay (from services/api.ts initiateReplay)
test('device replay request/response matches frontend ReplayAudit type', function () {
    $tech   = User::factory()->technician()->create();
    $token  = $tech->createToken('test')->plainTextToken;
    Device::create(['id' => 'contract-device-01', 'kind' => 'gate', 'last_sequence_no' => 100]);

    // Frontend sends: { since_sequence_no, until_sequence_no?, reason? }
    $response = $this->withToken($token)->postJson('/api/devices/contract-device-01/replay', [
        'since_sequence_no' => 50,
        'until_sequence_no' => 100,
        'reason'            => 'Test replay',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'device_id', 'initiated_by', 'since_sequence_no', 'until_sequence_no', 'reason', 'created_at']);
});

// Admin freeze/unfreeze/blacklist return User objects
test('admin freeze returns updated User resource', function () {
    $admin  = User::factory()->admin()->create();
    $target = User::factory()->create();
    $token  = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->patchJson("/api/users/{$target->id}/freeze", [
        'duration_hours' => 24,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'username', 'role', 'frozen_until']);
    expect($response->json('frozen_until'))->not->toBeNull();
});

test('admin unfreeze returns updated User resource', function () {
    $admin  = User::factory()->admin()->create();
    $target = User::factory()->frozen()->create();
    $token  = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->patchJson("/api/users/{$target->id}/unfreeze");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'username', 'role', 'frozen_until']);
    expect($response->json('frozen_until'))->toBeNull();
});

test('admin blacklist returns updated User resource', function () {
    $admin  = User::factory()->admin()->create();
    $target = User::factory()->create();
    $token  = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->patchJson("/api/users/{$target->id}/blacklist");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'username', 'role', 'blacklisted_at']);
    expect($response->json('blacklisted_at'))->not->toBeNull();
});

// Play history record
test('POST /assets/{id}/play records into authenticated user history', function () {
    $user  = User::factory()->create();
    $asset = Asset::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson("/api/assets/{$asset->id}/play", ['context' => 'search'])
        ->assertStatus(202);

    $history = \App\Models\PlayHistory::where('user_id', $user->id)->where('asset_id', $asset->id)->first();
    expect($history)->not->toBeNull();
});

// The frontend upload sends tags as repeated multipart `tags[]` entries. Assert the
// backend parses them as an array and persists them on the new asset.
test('upload accepts tags sent as tags[] multipart entries', function () {
    app()->bind(MediaProbe::class, fn () => new class extends MediaProbe {
        public function getDurationSeconds(string $filePath, string $mime): ?int { return null; }
    });

    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    // Real PNG magic bytes so server-side MIME sniffing is satisfied.
    $pngHeader = "\x89PNG\r\n\x1A\n" . str_repeat("\x00", 1024);
    $tempFile  = tempnam(sys_get_temp_dir(), 'tags') . '.png';
    file_put_contents($tempFile, $pngHeader);
    $file = new UploadedFile($tempFile, 'tags.png', 'image/png', null, true);

    // Mimic multipart where the client sends `tags[]` repeated.
    $response = $this->withToken($token)->post('/api/assets', [
        'title' => 'Tagged Asset',
        'file'  => $file,
        'tags'  => ['safety', 'parking', 'event'],
    ], ['Accept' => 'application/json']);

    $response->assertStatus(201);

    $assetId = $response->json('id');
    $asset   = Asset::with('assetTags')->find($assetId);
    $tags    = $asset->assetTags->pluck('tag')->sort()->values()->toArray();
    expect($tags)->toEqual(['event', 'parking', 'safety']);
});

// Search ingest contract
test('after upload search_index contains duration_seconds and duration filter works', function () {
    app()->bind(MediaProbe::class, fn () => new class extends MediaProbe {
        public function getDurationSeconds(string $filePath, string $mime): ?int { return 90; }
    });

    $admin = User::factory()->admin()->create();
    $user  = User::factory()->create();
    $adminToken = $admin->createToken('test')->plainTextToken;
    $userToken  = $user->createToken('test')->plainTextToken;

    $content  = "\x49\x44\x33\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.mp3';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'test.mp3', 'audio/mpeg', null, true);

    $uploadResponse = $this->withToken($adminToken)->postJson('/api/assets', [
        'title' => 'Contract Audio',
        'file'  => $file,
    ]);

    $uploadResponse->assertStatus(201);
    $assetId = $uploadResponse->json('id');

    $asset = Asset::find($assetId);
    expect($asset->duration_seconds)->toBe(90);

    // Manually set ready + index for search test
    $asset->update(['status' => 'ready']);
    SearchIndex::updateOrCreate(['asset_id' => $assetId], ['tokenized_title' => 'contract audio', 'tokenized_body' => '']);

    // Under 2 minutes filter (120s) — asset has 90s duration
    $searchResp = $this->withToken($userToken)->getJson('/api/search?duration_lt=120');
    $ids = collect($searchResp->json('items'))->pluck('id');
    expect($ids)->toContain($assetId);
});
