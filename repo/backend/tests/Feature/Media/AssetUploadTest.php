<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();
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

test('admin can upload a valid MP3 file', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test Audio',
        'file'  => makeMp3UploadFile(),
        'tags'  => ['safety', 'morning'],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'processing')
        ->assertJsonStructure(['id', 'title', 'status', 'mime']);
});

test('admin can upload a valid JPEG file', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test Image',
        'file'  => makeJpegUploadFile(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'processing');
});

test('upload with wrong MIME returns 422 with reason_code', function () {
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
        ->assertJsonPath('reason_code', 'magic_mismatch');
});

test('upload over size cap is rejected', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    // 26MB JPEG (over 25MB cap)
    $content  = "\xFF\xD8\xFF\xE0" . str_repeat('x', 26 * 1024 * 1024);
    $tempFile = tempnam(sys_get_temp_dir(), 'test') . '.jpg';
    file_put_contents($tempFile, $content);
    $file = new UploadedFile($tempFile, 'big.jpg', 'image/jpeg', null, true);

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Big Image',
        'file'  => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('reason_code', 'file_too_large');
});

test('non-admin cannot upload assets', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test',
        'file'  => makeMp3UploadFile(),
    ])->assertStatus(403);
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

test('PDF upload succeeds and asset status is ready after GenerateThumbnails job', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test PDF',
        'file'  => makePdfUploadFile(),
    ]);

    $response->assertStatus(201)->assertJsonPath('status', 'processing');

    $assetId = $response->json('id');

    // Manually dispatch the job synchronously
    $job = new \App\Jobs\GenerateThumbnails($assetId);
    $job->handle();

    $asset = \App\Models\Asset::find($assetId);
    expect($asset->status)->toBe('ready');
});

test('MP4 upload succeeds and asset status is ready after GenerateThumbnails job', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'Test MP4',
        'file'  => makeMp4UploadFile(),
    ]);

    $response->assertStatus(201)->assertJsonPath('status', 'processing');

    $assetId = $response->json('id');

    $job = new \App\Jobs\GenerateThumbnails($assetId);
    $job->handle();

    $asset = \App\Models\Asset::find($assetId);
    expect($asset->status)->toBe('ready');
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

    $response->assertStatus(422)->assertJsonPath('reason_code', 'mime_not_allowed');
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

    $response->assertStatus(422)->assertJsonPath('reason_code', 'mime_not_allowed');
});

test('get asset returns detail', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create();

    $response = $this->withToken($token)->getJson("/api/assets/{$asset->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'title', 'mime', 'status', 'tags']);
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

    // Simulate what GenerateThumbnails job would do when successful
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
