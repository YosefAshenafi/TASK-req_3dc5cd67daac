<?php

use App\Models\Asset;
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

test('audio/mpeg upload stores extracted duration_seconds', function () {
    // Bind a MediaProbe that always returns a known duration
    app()->bind(MediaProbe::class, fn () => new class extends MediaProbe {
        public function getDurationSeconds(string $filePath, string $mime): ?int
        {
            return 87; // 87 seconds
        }
    });

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

    $response->assertStatus(201);
    $assetId = $response->json('id');

    $asset = Asset::find($assetId);
    expect($asset->duration_seconds)->toBe(87);
});

test('video/mp4 upload stores extracted duration_seconds', function () {
    app()->bind(MediaProbe::class, fn () => new class extends MediaProbe {
        public function getDurationSeconds(string $filePath, string $mime): ?int
        {
            return 42;
        }
    });

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

    $response->assertStatus(201);
    $assetId = $response->json('id');

    $asset = Asset::find($assetId);
    expect($asset->duration_seconds)->toBe(42);
});

test('image upload has null duration_seconds', function () {
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

    $response->assertStatus(201);
    $assetId = $response->json('id');

    $asset = Asset::find($assetId);
    expect($asset->duration_seconds)->toBeNull();
});

test('short asset with duration appears in under_2_min search filter', function () {
    app()->bind(MediaProbe::class, fn () => new class extends MediaProbe {
        public function getDurationSeconds(string $filePath, string $mime): ?int
        {
            return 60; // 1 minute
        }
    });

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

    // Manually set status to ready and add to search_index
    $asset = Asset::find($assetId);
    $asset->update(['status' => 'ready']);
    \App\Models\SearchIndex::updateOrCreate(
        ['asset_id' => $assetId],
        ['tokenized_title' => 'short clip', 'tokenized_body' => '']
    );

    $searchResponse = $this->withToken($userToken)->getJson('/api/search?duration_lt=120');
    $ids = collect($searchResponse->json('items'))->pluck('id');
    expect($ids)->toContain($assetId);
});
