<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeNoMockJpegUploadFile(string $prefix = 'nomock'): UploadedFile
{
    // JPEG magic bytes: FF D8 FF E0
    $content  = "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 2048);
    $tempFile = tempnam(sys_get_temp_dir(), $prefix) . '.jpg';
    file_put_contents($tempFile, $content);
    return new UploadedFile($tempFile, "{$prefix}.jpg", 'image/jpeg', null, true);
}

test('admin can upload asset through real HTTP stack without test doubles', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/assets', [
        'title' => 'No-Mock Upload',
        'file'  => makeNoMockJpegUploadFile('upload'),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'processing')
        ->assertJsonPath('mime', 'image/jpeg');

    $assetId = (int) $response->json('id');
    $asset   = Asset::findOrFail($assetId);

    expect($asset->uploaded_by)->toBe($admin->id);
    expect($asset->file_path)->not->toBe('');
    expect(Storage::disk('local')->exists($asset->file_path))->toBeTrue();
});

test('admin can delete unreferenced asset through real HTTP stack without test doubles', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;
    $asset = Asset::factory()->create([
        'uploaded_by' => $admin->id,
        'status'      => 'ready',
    ]);

    $this->withToken($token)->deleteJson("/api/assets/{$asset->id}")
        ->assertStatus(204);

    expect(Asset::find($asset->id))->toBeNull();
    expect(Asset::withTrashed()->find($asset->id))->not->toBeNull();
});

test('admin can replace asset through real HTTP stack without test doubles', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $oldAsset = Asset::factory()->create([
        'uploaded_by' => $admin->id,
        'status'      => 'ready',
    ]);

    $response = $this->withToken($token)->postJson("/api/admin/assets/{$oldAsset->id}/replace", [
        'title' => 'No-Mock Replacement',
        'file'  => makeNoMockJpegUploadFile('replace'),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('old_asset_id', $oldAsset->id)
        ->assertJsonStructure(['new_asset_id', 'remapped_playlists', 'remapped_favorites', 'remapped_history']);

    $newAssetId = (int) $response->json('new_asset_id');

    expect($newAssetId)->not->toBe($oldAsset->id);
    expect(Asset::find($newAssetId))->not->toBeNull();
    expect(Asset::withTrashed()->find($oldAsset->id)?->deleted_at)->not->toBeNull();
});
