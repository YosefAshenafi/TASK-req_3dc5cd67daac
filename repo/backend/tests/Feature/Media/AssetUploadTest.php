<?php

use App\Models\Asset;
use App\Models\SearchIndex;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// No Queue::fake(): the test phpunit config pins QUEUE_CONNECTION=sync, so
// dispatched jobs (GenerateThumbnails, IndexAsset, MediaScanRequested) actually
// run inline and we assert their *observable* side effects on the DB and
// storage instead of just that a class name was pushed to the queue.
beforeEach(function () {
    // Storage::fake redirects the `local`/`public` disks to an in-memory storage
    // root for the duration of the test. This is isolation, not production-path
    // avoidance: the controller still calls $uploadedFile->store('media', 'local'),
    // we just verify the written bytes land on the fake disk instead of polluting
    // the real storage/ directory between test runs.
    Storage::fake('local');
    Storage::fake('public');
});

function makeMp3UploadFile(): UploadedFile
{
    // ID3v2 header: "ID3" = 0x49 0x44 0x33
    $content  = "\x49\x44\x33\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.mp3';
    file_put_contents($tempFile, $content);
    return new UploadedFile($tempFile, 'test.mp3', 'audio/mpeg', null, true);
}

function makeJpegUploadFile(): UploadedFile
{
    // JPEG magic bytes: FF D8 FF E0
    $content  = "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.jpg';
    file_put_contents($tempFile, $content);
    return new UploadedFile($tempFile, 'test.jpg', 'image/jpeg', null, true);
}

test('admin can upload a valid MP3 file and the full post-upload pipeline runs', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test Audio',
        'file'  => makeMp3UploadFile(),
        'tags'  => ['safety', 'morning'],
    ]);

    // The JSON response is rendered from the in-memory $asset the controller
    // just created, so status is 'processing' in the HTTP body. The DB row,
    // however, is post-sync-job and should already be 'ready'.
    $response->assertStatus(201)
        ->assertJsonPath('status', 'processing')
        ->assertJsonPath('mime', 'audio/mpeg')
        ->assertJsonPath('title', 'Test Audio')
        ->assertJsonStructure(['id', 'title', 'status', 'mime', 'duration_seconds']);

    $assetId = (int) $response->json('id');
    $asset   = Asset::with('assetTags')->findOrFail($assetId);

    expect($asset->uploaded_by)->toBe($admin->id);
    expect($asset->mime)->toBe('audio/mpeg');
    expect($asset->size_bytes)->toBe(1034); // 10-byte ID3 header + 1024 zero bytes
    expect(strlen((string) $asset->fingerprint_sha256))->toBe(64);
    expect($asset->file_path)->toStartWith('media/');
    expect(Storage::disk('local')->exists($asset->file_path))->toBeTrue();

    // GenerateThumbnails ran synchronously: for non-image mimes it short-circuits
    // and transitions the row to 'ready' without writing any thumbnail.
    expect($asset->status)->toBe('ready');

    // IndexAsset ran synchronously: search_index row must exist with a tokenized
    // form of the title (lowercased, alphanum-only). Missing row = IndexAsset
    // never ran = the upload is silently unsearchable.
    $index = SearchIndex::where('asset_id', $assetId)->first();
    expect($index)->not->toBeNull();
    expect($index->tokenized_title)->toBe('test audio');

    // Tags persist through the assetTags relation, not a scalar column.
    $tags = $asset->assetTags->pluck('tag')->sort()->values()->all();
    expect($tags)->toEqual(['morning', 'safety']);
});

test('admin can upload a valid JPEG file and status transitions to ready', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test Image',
        'file'  => makeJpegUploadFile(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'processing')
        ->assertJsonPath('mime', 'image/jpeg')
        ->assertJsonPath('duration_seconds', null);

    // Images never have a duration_seconds — MediaProbe short-circuits on
    // image/* without even invoking ffprobe.
    $assetId = (int) $response->json('id');
    $asset   = Asset::findOrFail($assetId);
    expect($asset->duration_seconds)->toBeNull();
    expect(Storage::disk('local')->exists($asset->file_path))->toBeTrue();

    // The sync-dispatched GenerateThumbnails handler tries to read the 4-byte
    // fake JPEG, per-size read/decode fails and is caught — but the final
    // status update to 'ready' still fires (intentional: we don't want a bad
    // image's thumbnail failure to block the whole upload).
    expect($asset->status)->toBe('ready');

    // Search indexing is independent of thumbnail success — IndexAsset must
    // still have written a row.
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();
});

test('upload with wrong MIME returns 422 with reason_code and stable error shape', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    // PNG content (89 50 4E 47) declared as MP4 → magic mismatch
    $content  = "\x89\x50\x4E\x47\r\n\x1A\n" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.mp4';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'disguised.mp4', 'video/mp4', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Disguised',
        'file'  => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'reason_code'])
        ->assertJsonPath('reason_code', 'magic_mismatch');

    // The error payload must carry a human message, not just a code — this is
    // what the admin upload view renders in its failure toast.
    expect($response->json('message'))->toBeString()->not->toBeEmpty();

    // No Asset row, no search index row — validation stopped the pipeline
    // before any side-effect could happen.
    expect(Asset::count())->toBe(0);
    expect(SearchIndex::count())->toBe(0);
});

test('upload over size cap is rejected with file_too_large reason_code', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    // 26MB JPEG (over 25MB cap) — written in chunks to avoid exhausting PHP memory.
    $tempFile = write_oversized_jpeg_temp_path();
    $file = new UploadedFile($tempFile, 'big.jpg', 'image/jpeg', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Big Image',
        'file'  => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'reason_code'])
        ->assertJsonPath('reason_code', 'file_too_large');

    expect($response->json('message'))->toContain('25'); // message must mention the cap in MB
    expect(Asset::count())->toBe(0);
    expect(SearchIndex::count())->toBe(0);
});

test('upload with missing title returns 422 with Laravel validation errors payload', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'file' => makeMp3UploadFile(),
        // title intentionally omitted
    ]);

    // Laravel's validator returns a flat errors map keyed by field name.
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);

    expect(Asset::count())->toBe(0);
    expect(SearchIndex::count())->toBe(0);
});

test('upload with missing file returns 422 with errors.file', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Missing File',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['file']);
    expect(Asset::count())->toBe(0);
});

test('upload with oversized tag entry is rejected with errors on tags.*', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Tag Too Long',
        'file'  => makeMp3UploadFile(),
        'tags'  => [str_repeat('x', 60)], // max:50 → validation fail
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['tags.0']);
    expect(Asset::count())->toBe(0);
});

test('non-admin cannot upload assets and no pipeline side effects are produced', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test',
        'file'  => makeMp3UploadFile(),
    ]);

    $response->assertStatus(403);
    expect(Asset::count())->toBe(0);
    expect(SearchIndex::count())->toBe(0);
});

test('unauthenticated upload is rejected with 401 and a JSON message', function () {
    $response = $this->postJson('/api/assets', [
        'title' => 'Test',
        'file'  => makeMp3UploadFile(),
    ]);

    $response->assertStatus(401);
    expect($response->json())->toBeArray();
    expect($response->json('message'))->toBeString()->not->toBeEmpty();
    expect(Asset::count())->toBe(0);
});

function makePdfUploadFile(): UploadedFile
{
    // PDF magic bytes: %PDF = 0x25 0x50 0x44 0x46
    $content  = "\x25\x50\x44\x46\x2D\x31\x2E\x34" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.pdf';
    file_put_contents($tempFile, $content);
    return new UploadedFile($tempFile, 'test.pdf', 'application/pdf', null, true);
}

function makeMp4UploadFile(): UploadedFile
{
    // MP4: ftyp box at bytes 4-7 = 0x66 0x74 0x79 0x70
    $content  = "\x00\x00\x00\x18\x66\x74\x79\x70\x69\x73\x6F\x6D" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.mp4';
    file_put_contents($tempFile, $content);
    return new UploadedFile($tempFile, 'test.mp4', 'video/mp4', null, true);
}

test('PDF upload succeeds and the sync GenerateThumbnails job transitions to ready', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test PDF',
        'file'  => makePdfUploadFile(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'processing')
        ->assertJsonPath('mime', 'application/pdf');

    $assetId = $response->json('id');

    // No need to manually run the job — QUEUE_CONNECTION=sync already executed
    // it inside the dispatch call. Verify the resulting DB state directly.
    $asset = Asset::find($assetId);
    expect($asset->status)->toBe('ready');
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();
});

test('MP4 upload succeeds and asset status is ready after sync pipeline runs', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test MP4',
        'file'  => makeMp4UploadFile(),
    ]);

    $response->assertStatus(201)->assertJsonPath('status', 'processing');

    $assetId = $response->json('id');

    $asset = Asset::find($assetId);
    expect($asset->status)->toBe('ready');
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();
});

test('video/webm upload is rejected with 422 and mime_not_allowed', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $content  = str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.webm';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'test.webm', 'video/webm', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test WebM',
        'file'  => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'reason_code'])
        ->assertJsonPath('reason_code', 'mime_not_allowed');
    expect($response->json('message'))->toContain('video/webm');
    expect(Asset::count())->toBe(0);
});

test('audio/wav upload is rejected with 422 and mime_not_allowed', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $content  = str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.wav';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'test.wav', 'audio/wav', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test WAV',
        'file'  => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'reason_code'])
        ->assertJsonPath('reason_code', 'mime_not_allowed');
    expect(Asset::count())->toBe(0);
});

test('get asset returns detail with the expected shape', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create();

    $response = $this->withToken($token)->getJson("/api/assets/{$asset->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'title', 'mime', 'status', 'tags'])
        ->assertJsonPath('id', $asset->id)
        ->assertJsonPath('title', $asset->title);
});

test('non-admin cannot see file_path or fingerprint_sha256 in asset detail', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create();

    $response = $this->withToken($token)->getJson("/api/assets/{$asset->id}");

    $response->assertStatus(200);
    $body = $response->json();
    expect($body)->not->toHaveKey('file_path');
    expect($body)->not->toHaveKey('fingerprint_sha256');
});

test('admin can see file_path and fingerprint_sha256 in asset detail', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create();

    $response = $this->withToken($token)->getJson("/api/assets/{$asset->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'file_path', 'fingerprint_sha256']);
});

test('JPEG upload exposes thumbnail_urls in asset detail when set', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Thumbnail Test',
        'file'  => makeJpegUploadFile(),
    ]);

    $response->assertStatus(201);
    $assetId = $response->json('id');

    // Simulate what GenerateThumbnails would do when successful (the job under
    // sync mode tried but failed against our 4-byte fake JPEG — here we set
    // the same state a successful decode would have produced).
    $asset = Asset::find($assetId);
    $asset->update([
        'status'         => 'ready',
        'thumbnail_urls' => [
            '160' => "thumbs/{$assetId}/160.jpg",
            '480' => "thumbs/{$assetId}/480.jpg",
            '960' => "thumbs/{$assetId}/960.jpg",
        ],
    ]);

    $detailResponse = $this->withToken($token)->getJson("/api/assets/{$assetId}");
    $thumbnailUrls  = $detailResponse->json('thumbnail_urls');

    expect($thumbnailUrls)->not->toBeNull();
    expect($thumbnailUrls)->toHaveKey('160');
    expect($thumbnailUrls)->toHaveKey('480');
    expect($thumbnailUrls)->toHaveKey('960');
});

test('non-admin requesting a non-ready asset gets 404 rather than 403 to avoid leaking existence', function () {
    $user    = User::factory()->create(['role' => 'user']);
    $token   = $user->createToken('test')->plainTextToken;
    $asset   = Asset::factory()->create(['status' => 'processing']);

    $response = $this->withToken($token)->getJson("/api/assets/{$asset->id}");

    // Deliberate 404: exposing a 403 here would confirm the asset's existence
    // to a non-admin that otherwise shouldn't know about the admin queue.
    $response->assertStatus(404)
        ->assertJsonStructure(['message']);
    expect($response->json('message'))->toBe('Asset not found.');
});

test('uploading the same bytes twice yields two distinct rows with matching fingerprints (no dedupe)', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $r1 = $this->withToken($token)->postJson('/api/assets', ['title' => 'A', 'file' => makeJpegUploadFile()]);
    $r2 = $this->withToken($token)->postJson('/api/assets', ['title' => 'B', 'file' => makeJpegUploadFile()]);

    $r1->assertStatus(201);
    $r2->assertStatus(201);

    $a1 = Asset::findOrFail($r1->json('id'));
    $a2 = Asset::findOrFail($r2->json('id'));

    // Bytes are identical → sha256 must match. Rows are distinct so file_paths
    // differ. This guards against an accidental "dedupe by fingerprint" bug
    // that would quietly drop the second upload.
    expect($a1->id)->not->toBe($a2->id);
    expect($a1->title)->toBe('A');
    expect($a2->title)->toBe('B');
    expect($a1->fingerprint_sha256)->toBe($a2->fingerprint_sha256);
    expect($a1->file_path)->not->toBe($a2->file_path);
    expect($a1->uploaded_by)->toBe($admin->id);
    expect($a2->uploaded_by)->toBe($admin->id);

    // Both rows end up searchable independently (IndexAsset ran for both).
    expect(SearchIndex::where('asset_id', $a1->id)->exists())->toBeTrue();
    expect(SearchIndex::where('asset_id', $a2->id)->exists())->toBeTrue();
});
