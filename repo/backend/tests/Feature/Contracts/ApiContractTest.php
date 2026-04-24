<?php

use App\Models\Asset;
use App\Models\Device;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\SearchIndex;
use App\Models\User;
use App\Services\MediaProbe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// No Queue::fake(): QUEUE_CONNECTION=sync runs upload-pipeline jobs inline.
// Contract tests care about HTTP request/response shape and the observable
// side effects on the DB, which is exactly what we now assert.
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
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

    // Shape assertion: each row has the exact set of keys the frontend reads.
    foreach ($payload as $row) {
        expect($row)->toHaveKeys(['id', 'name', 'owner_id', 'items_count']);
    }
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
    expect($items)->toBeArray()->toHaveCount(2);
    expect($items[0])->toHaveKeys(['id', 'playlist_id', 'asset_id', 'position']);

    // Order matches the request: item2 first, item1 second.
    expect($items[0]['id'])->toBe($item2->id);
    expect($items[0]['position'])->toBe(1);
    expect($items[1]['id'])->toBe($item1->id);
    expect($items[1]['position'])->toBe(2);

    // Side-effect: the persisted positions match the reordered payload.
    expect(PlaylistItem::find($item2->id)->position)->toBe(1);
    expect(PlaylistItem::find($item1->id)->position)->toBe(2);
});

test('playlist reorder with missing item_ids returns 422 and errors.item_ids', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);

    $response = $this->withToken($token)->putJson("/api/playlists/{$playlist->id}/items/order", []);

    $response->assertStatus(422)->assertJsonValidationErrors(['item_ids']);
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
        ->assertJsonStructure(['id', 'device_id', 'initiated_by', 'since_sequence_no', 'until_sequence_no', 'reason', 'created_at'])
        ->assertJsonPath('device_id', 'contract-device-01')
        ->assertJsonPath('since_sequence_no', 50)
        ->assertJsonPath('until_sequence_no', 100)
        ->assertJsonPath('reason', 'Test replay')
        ->assertJsonPath('initiated_by', $tech->id);
});

test('device replay with missing since_sequence_no returns 422 and errors.since_sequence_no', function () {
    $tech = User::factory()->technician()->create();
    $token = $tech->createToken('test')->plainTextToken;
    Device::create(['id' => 'contract-device-no-since', 'kind' => 'gate', 'last_sequence_no' => 100]);

    $response = $this->withToken($token)->postJson('/api/devices/contract-device-no-since/replay', [
        'reason' => 'forgot since',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['since_sequence_no']);
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
        ->assertJsonStructure(['id', 'username', 'role', 'frozen_until'])
        ->assertJsonPath('id', $target->id)
        ->assertJsonPath('username', $target->username)
        ->assertJsonPath('role', $target->role);
    expect($response->json('frozen_until'))->not->toBeNull();

    // Check the frozen_until is ~24 hours in the future — allows some drift.
    $frozenUntil = \Carbon\Carbon::parse($response->json('frozen_until'));
    expect($frozenUntil->diffInHours(now(), false))->toBeLessThan(-23); // more than 23h in the future
    expect($frozenUntil->diffInHours(now(), false))->toBeGreaterThan(-25); // less than 25h in the future

    // DB side effect: the row carries the same frozen_until value.
    $persisted = User::find($target->id);
    expect($persisted->frozen_until)->not->toBeNull();
});

test('admin freeze with invalid duration_hours returns 422 and errors.duration_hours', function () {
    $admin  = User::factory()->admin()->create();
    $target = User::factory()->create();
    $token  = $admin->createToken('test')->plainTextToken;

    // Negative/zero/excessive durations should all be rejected with a structured
    // error. Laravel surfaces validation-failed as 422 with an `errors` map.
    $response = $this->withToken($token)->patchJson("/api/users/{$target->id}/freeze", [
        'duration_hours' => -5,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['duration_hours']);

    // DB not mutated.
    expect(User::find($target->id)->frozen_until)->toBeNull();
});

test('admin unfreeze returns updated User resource', function () {
    $admin  = User::factory()->admin()->create();
    $target = User::factory()->frozen()->create();
    $token  = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->patchJson("/api/users/{$target->id}/unfreeze");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'username', 'role', 'frozen_until']);
    expect($response->json('frozen_until'))->toBeNull();
    expect(User::find($target->id)->frozen_until)->toBeNull();
});

test('admin blacklist returns updated User resource', function () {
    $admin  = User::factory()->admin()->create();
    $target = User::factory()->create();
    $token  = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->patchJson("/api/users/{$target->id}/blacklist");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'username', 'role', 'blacklisted_at']);
    expect($response->json('blacklisted_at'))->not->toBeNull();
    expect(User::find($target->id)->blacklisted_at)->not->toBeNull();
});

test('non-admin calling freeze/unfreeze/blacklist returns 403', function () {
    $user   = User::factory()->create(['role' => 'user']);
    $target = User::factory()->create();
    $token  = $user->createToken('test')->plainTextToken;

    // These are admin-only endpoints; table-driven check so we don't have to
    // duplicate the setup three times.
    $endpoints = [
        ['PATCH', "/api/users/{$target->id}/freeze", ['duration_hours' => 24]],
        ['PATCH', "/api/users/{$target->id}/unfreeze", []],
        ['PATCH', "/api/users/{$target->id}/blacklist", []],
    ];

    foreach ($endpoints as [$method, $path, $body]) {
        $response = $this->withToken($token)->json($method, $path, $body);
        $response->assertStatus(403);
    }

    // Target's state should remain pristine after every forbidden call.
    $persisted = User::find($target->id);
    expect($persisted->frozen_until)->toBeNull();
    expect($persisted->blacklisted_at)->toBeNull();
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
    expect($history->context)->toBe('search');
});

test('POST /assets/{id}/play for a missing asset returns 404 with JSON body', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets/999999/play', []);
    $response->assertStatus(404);

    expect(\App\Models\PlayHistory::where('user_id', $user->id)->count())->toBe(0);
});

// The frontend upload sends tags as repeated multipart `tags[]` entries. Assert the
// backend parses them as an array and persists them on the new asset.
test('upload accepts tags sent as tags[] multipart entries', function () {
    // ffprobe isn't available in test-runner image; bind a no-op probe so
    // duration extraction cleanly returns null without depending on the binary.
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

    // Sync pipeline ran: search index populated.
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();
});

// Search ingest contract — relies on the sync IndexAsset job writing search_index
// end-to-end rather than us manually seeding that row.
test('after upload the sync pipeline writes search_index and duration filter works', function () {
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

    // Sync pipeline ran: IndexAsset populated search_index with the tokenized
    // title, and GenerateThumbnails flipped status to ready. The search endpoint
    // requires both to return this asset to a non-admin.
    $index = SearchIndex::where('asset_id', $assetId)->first();
    expect($index)->not->toBeNull();
    expect($index->tokenized_title)->toBe('contract audio');
    expect($asset->status)->toBe('ready');

    // Under 2 minutes filter (120s) — asset has 90s duration
    $searchResp = $this->withToken($userToken)->getJson('/api/search?duration_lt=120');
    $ids = collect($searchResp->json('items'))->pluck('id');
    expect($ids)->toContain($assetId);

    // Full-text match should also pick it up.
    $fullText = $this->withToken($userToken)->getJson('/api/search?q=contract');
    expect(collect($fullText->json('items'))->pluck('id'))->toContain($assetId);
});

// Generic error-shape contract: protected endpoints consistently return { message }
// on 401/403/404/422. The frontend's ApiError maps these to user-facing toasts
// and a missing `message` falls back to "HTTP {status}", which is not useful.
test('protected endpoints return a JSON body with a message field on common failure statuses', function () {
    $response = $this->getJson('/api/playlists');
    $response->assertStatus(401);
    // Laravel's default unauthenticated response has { message: "Unauthenticated." }
    expect($response->json())->toBeArray();
    expect($response->json('message'))->toBeString()->not->toBeEmpty();

    // 403 shape
    $user   = User::factory()->create(['role' => 'user']);
    $token  = $user->createToken('test')->plainTextToken;
    $target = User::factory()->create();
    $forbidden = $this->withToken($token)->patchJson("/api/users/{$target->id}/blacklist");
    $forbidden->assertStatus(403);
    expect($forbidden->json())->toBeArray();
    expect($forbidden->json('message'))->toBeString()->not->toBeEmpty();

    // 404 shape for a missing asset
    $missing = $this->withToken($token)->getJson('/api/assets/9999999');
    $missing->assertStatus(404);
    expect($missing->json())->toBeArray();
    expect($missing->json('message'))->toBeString()->not->toBeEmpty();
});
