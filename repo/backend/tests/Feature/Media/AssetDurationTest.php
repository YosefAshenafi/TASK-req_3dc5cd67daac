<?php

use App\Models\Asset;
use App\Models\SearchIndex;
use App\Models\User;
use App\Services\MediaProbe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// No Queue::fake(): QUEUE_CONNECTION=sync in phpunit config means dispatched
// jobs run inline. We keep only the MediaProbe DI override because the
// test-runner image does not include the ffmpeg/ffprobe binaries required
// for real duration extraction (documented inline on each override).
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/**
 * The only mock here is MediaProbe itself. We avoid injecting ffprobe test
 * fixtures because the test runner image does not ship with ffmpeg; binding
 * a stub that returns a known integer still exercises the full HTTP
 * controller → validator → storage → DB path and just takes the probe
 * output as given. Every test that calls this explains *why* a specific
 * probe outcome is being simulated.
 */
function stubMediaProbeReturning(?int $duration): void
{
    app()->bind(MediaProbe::class, fn () => new class($duration) extends MediaProbe {
        public function __construct(private readonly ?int $duration) {}
        public function getDurationSeconds(string $filePath, string $mime): ?int
        {
            return $this->duration;
        }
    });
}

test('audio/mpeg upload stores extracted duration_seconds and exposes it in response', function () {
    // ffprobe returning 87s: simulate a valid MP3 that completes probing.
    stubMediaProbeReturning(87);

    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $content  = "\x49\x44\x33\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.mp3';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'sample.mp3', 'audio/mpeg', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test Audio',
        'file'  => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('duration_seconds', 87)
        ->assertJsonPath('mime', 'audio/mpeg');

    $assetId = $response->json('id');
    $asset   = Asset::findOrFail($assetId);
    expect($asset->duration_seconds)->toBe(87);
    expect($asset->size_bytes)->toBeGreaterThan(0);

    // Sync IndexAsset job must have written a search_index row carrying the
    // tokenized title. Missing row = the post-upload pipeline did not run.
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();
});

test('video/mp4 upload stores extracted duration_seconds', function () {
    // ffprobe returning 42s on a probe-able MP4.
    stubMediaProbeReturning(42);

    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $content  = "\x00\x00\x00\x18\x66\x74\x79\x70\x69\x73\x6F\x6D" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.mp4';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'sample.mp4', 'video/mp4', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test Video',
        'file'  => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('duration_seconds', 42)
        ->assertJsonPath('mime', 'video/mp4');

    $assetId = $response->json('id');
    expect(Asset::findOrFail($assetId)->duration_seconds)->toBe(42);
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();
});

test('image upload has null duration_seconds — no probe binding required', function () {
    // Note: no stubMediaProbeReturning() call. The real MediaProbe short-circuits
    // for non-audio/video MIMEs before invoking ffprobe, so this exercises the
    // production path end-to-end without any DI override.
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $content  = "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.jpg';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'photo.jpg', 'image/jpeg', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test Image',
        'file'  => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('duration_seconds', null);

    $assetId = $response->json('id');
    expect(Asset::findOrFail($assetId)->duration_seconds)->toBeNull();
    // Sync pipeline still indexes the image.
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();
});

test('pdf upload has null duration_seconds regardless of probe behaviour', function () {
    // Even if a future MediaProbe decided to return something for PDFs, the
    // real path should still yield null for non-av MIMEs.
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $content  = "\x25\x50\x44\x46\x2D\x31\x2E\x34" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.pdf';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'doc.pdf', 'application/pdf', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Doc',
        'file'  => $file,
    ]);

    $response->assertStatus(201)->assertJsonPath('duration_seconds', null);
    $assetId = $response->json('id');
    expect(Asset::findOrFail($assetId)->duration_seconds)->toBeNull();
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();
});

test('mp3 upload with probe returning null still succeeds with null duration', function () {
    // Simulates ffprobe failing or a corrupt-but-magic-valid MP3 — upload must
    // NOT reject the file, just store a null duration.
    stubMediaProbeReturning(null);

    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $content  = "\x49\x44\x33\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.mp3';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'broken.mp3', 'audio/mpeg', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Broken MP3',
        'file'  => $file,
    ]);

    $response->assertStatus(201)->assertJsonPath('duration_seconds', null);
    $assetId = $response->json('id');
    expect(Asset::findOrFail($assetId)->duration_seconds)->toBeNull();
    // Even a duration-less asset still gets indexed.
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();
});

test('short asset with duration appears in under_2_min search filter', function () {
    stubMediaProbeReturning(60); // 1 minute

    $user  = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $userToken  = $user->createToken('test')->plainTextToken;
    $adminToken = $admin->createToken('test')->plainTextToken;

    $content  = "\x49\x44\x33\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.mp3';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'short.mp3', 'audio/mpeg', null, true);

    $uploadResponse = $this->withToken($adminToken)->postJson('/api/assets', [
        'title' => 'Short Clip',
        'file'  => $file,
    ]);
    $uploadResponse->assertStatus(201);
    $assetId = $uploadResponse->json('id');

    // IndexAsset ran sync and populated search_index. Non-admin listing requires
    // status='ready' — the sync GenerateThumbnails handler already flipped it.
    $asset = Asset::find($assetId);
    expect($asset->status)->toBe('ready');
    expect(SearchIndex::where('asset_id', $assetId)->exists())->toBeTrue();

    $searchResponse = $this->withToken($userToken)->getJson('/api/search?duration_lt=120');
    $ids = collect($searchResponse->json('items'))->pluck('id');
    expect($ids)->toContain($assetId);

    // Inverse assertion: with a stricter upper bound, the 60-second asset must
    // NOT appear. This catches a bug where duration_lt was accidentally ignored.
    $tighter = $this->withToken($userToken)->getJson('/api/search?duration_lt=30');
    $tighterIds = collect($tighter->json('items'))->pluck('id');
    expect($tighterIds)->not->toContain($assetId);
});

test('asset detail exposes duration_seconds from the persisted row, not a re-probe', function () {
    stubMediaProbeReturning(123);

    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $content  = "\x49\x44\x33\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.mp3';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'persist.mp3', 'audio/mpeg', null, true);

    $uploadResponse = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Persist',
        'file'  => $file,
    ]);
    $assetId = $uploadResponse->json('id');

    // Rebind the probe to a different value. The detail endpoint should still
    // return 123 because it reads from the DB, not the probe.
    stubMediaProbeReturning(999);

    $detail = $this->withToken($token)->getJson("/api/assets/{$assetId}");
    $detail->assertStatus(200)->assertJsonPath('duration_seconds', 123);
});
