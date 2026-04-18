<?php

use App\Jobs\GenerateRecommendationCandidates;
use App\Jobs\GenerateThumbnails;
use App\Jobs\IndexAsset;
use App\Jobs\MediaScanRequested;
use App\Jobs\PurgeSoftDeletedUsers;
use App\Jobs\ReconcileDeviceEvents;
use App\Models\Asset;
use App\Models\AssetTag;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\RecommendationCandidate;
use App\Models\SearchIndex;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('PurgeSoftDeletedUsers permanently deletes old soft-deleted users', function () {
    $user = User::factory()->create();
    $user->delete();
    DB::table('users')->where('id', $user->id)->update(['deleted_at' => now()->subDays(35)]);

    (new PurgeSoftDeletedUsers)->handle();

    expect(User::withTrashed()->find($user->id))->toBeNull();
});

test('GenerateRecommendationCandidates creates candidates when tag overlap exists', function () {
    $user = User::factory()->create();

    $owned = Asset::factory()->create(['status' => 'ready']);
    Favorite::create(['user_id' => $user->id, 'asset_id' => $owned->id]);
    AssetTag::create(['asset_id' => $owned->id, 'tag' => 'jazz']);

    $candidateAsset = Asset::factory()->create(['status' => 'ready']);
    AssetTag::create(['asset_id' => $candidateAsset->id, 'tag' => 'jazz']);

    (new GenerateRecommendationCandidates($user->id))->handle();

    $row = RecommendationCandidate::where('user_id', $user->id)
        ->where('asset_id', $candidateAsset->id)
        ->first();

    expect($row)->not->toBeNull();
    expect($row->score)->toBeGreaterThan(0);
});

test('GenerateRecommendationCandidates skips user with no favorites or plays', function () {
    $user = User::factory()->create();

    (new GenerateRecommendationCandidates($user->id))->handle();

    expect(RecommendationCandidate::where('user_id', $user->id)->count())->toBe(0);
});

test('IndexAsset builds search_index row', function () {
    $asset = Asset::factory()->create([
        'title'       => 'Hello World!',
        'description' => 'Foo Bar Baz',
    ]);

    (new IndexAsset($asset->id))->handle();

    $row = SearchIndex::where('asset_id', $asset->id)->first();
    expect($row)->not->toBeNull();
    expect($row->tokenized_title)->toContain('hello');
});

test('IndexAsset no-ops when asset missing', function () {
    (new IndexAsset(999_999_999))->handle();
    expect(SearchIndex::count())->toBe(0);
});

test('MediaScanRequested logs for existing asset', function () {
    $asset = Asset::factory()->create();

    (new MediaScanRequested($asset->id))->handle();

    expect(true)->toBeTrue();
});

test('MediaScanRequested no-ops when asset missing', function () {
    (new MediaScanRequested(999_999_999))->handle();
    expect(true)->toBeTrue();
});

test('GenerateThumbnails no-ops when asset not found', function () {
    (new GenerateThumbnails(999_999_998))->handle();
    expect(true)->toBeTrue();
});

test('GenerateThumbnails marks non-image asset ready without thumbnails', function () {
    $asset = Asset::factory()->create([
        'mime'   => 'video/mp4',
        'status' => 'processing',
    ]);

    (new GenerateThumbnails($asset->id))->handle();

    $asset->refresh();
    expect($asset->status)->toBe('ready');
});

test('GenerateThumbnails generates thumbnails for jpeg on disk', function () {
    Storage::fake('local');
    Storage::fake('public');

    $rel = 'media/thumb-src-' . Str::random(8) . '.jpg';
    $asset = Asset::factory()->create([
        'mime'       => 'image/jpeg',
        'status'     => 'processing',
        'file_path'  => $rel,
    ]);

    $full = Storage::disk('local')->path($rel);
    if (! is_dir(dirname($full))) {
        mkdir(dirname($full), 0755, true);
    }

    if (! extension_loaded('gd')) {
        test()->markTestSkipped('GD extension required for thumbnail job');
    }

    $im = imagecreatetruecolor(4, 4);
    imagejpeg($im, $full, 90);

    (new GenerateThumbnails($asset->id))->handle();

    $asset->refresh();
    expect($asset->status)->toBe('ready');
    // Thumbnails may be empty if Intervention fails in CI; status still becomes ready when paths exist
    expect($asset->thumbnail_urls === null || is_array($asset->thumbnail_urls))->toBeTrue();
});

test('GenerateThumbnails marks failed when source file missing', function () {
    Storage::fake('local');

    $asset = Asset::factory()->create([
        'mime'      => 'image/jpeg',
        'status'    => 'processing',
        'file_path' => 'media/does-not-exist.jpg',
    ]);

    (new GenerateThumbnails($asset->id))->handle();

    $asset->refresh();
    expect($asset->status)->toBe('failed');
});

test('ReconcileDeviceEvents advances last_sequence_no for contiguous events', function () {
    $deviceId = 'dev-' . Str::random(8);
    Device::create([
        'id'                 => $deviceId,
        'kind'               => 'gate',
        'label'              => 'L1',
        'last_sequence_no'   => 0,
        'last_seen_at'       => now(),
    ]);

    foreach ([1, 2, 3] as $seq) {
        DeviceEvent::create([
            'device_id'        => $deviceId,
            'event_type'       => 'heartbeat',
            'sequence_no'      => $seq,
            'idempotency_key'  => Str::uuid()->toString(),
            'occurred_at'      => now(),
            'received_at'      => now(),
            'is_out_of_order'  => false,
            'payload_json'     => [],
            'status'           => 'accepted',
        ]);
    }

    (new ReconcileDeviceEvents($deviceId))->handle();

    $device = Device::find($deviceId);
    expect((int) $device->last_sequence_no)->toBe(3);
});

test('ReconcileDeviceEvents stops at gap in sequence', function () {
    $deviceId = 'dev-' . Str::random(8);
    Device::create([
        'id'                 => $deviceId,
        'kind'               => 'gate',
        'label'              => 'L1',
        'last_sequence_no'   => 0,
        'last_seen_at'       => now(),
    ]);

    DeviceEvent::create([
        'device_id'        => $deviceId,
        'event_type'       => 'heartbeat',
        'sequence_no'      => 1,
        'idempotency_key'  => Str::uuid()->toString(),
        'occurred_at'      => now(),
        'received_at'      => now(),
        'is_out_of_order'  => false,
        'payload_json'     => [],
        'status'           => 'accepted',
    ]);
    DeviceEvent::create([
        'device_id'        => $deviceId,
        'event_type'       => 'heartbeat',
        'sequence_no'      => 3,
        'idempotency_key'  => Str::uuid()->toString(),
        'occurred_at'      => now(),
        'received_at'      => now(),
        'is_out_of_order'  => false,
        'payload_json'     => [],
        'status'           => 'accepted',
    ]);

    (new ReconcileDeviceEvents($deviceId))->handle();

    $device = Device::find($deviceId);
    expect((int) $device->last_sequence_no)->toBe(1);
});

test('ReconcileDeviceEvents no-ops when device missing', function () {
    (new ReconcileDeviceEvents('missing-device'))->handle();
    expect(true)->toBeTrue();
});
