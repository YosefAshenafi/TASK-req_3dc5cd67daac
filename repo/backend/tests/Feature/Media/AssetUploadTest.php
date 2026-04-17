<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
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

test('get asset returns detail', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create();

    $response = $this->withToken($token)->getJson("/api/assets/{$asset->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'title', 'mime', 'status', 'tags']);
});
