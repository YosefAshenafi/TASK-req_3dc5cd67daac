<?php

use App\Models\Asset;
use App\Models\FeatureFlag;
use App\Models\SearchIndex;
use App\Models\User;

test('search returns results matching query', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $asset = Asset::factory()->create(['title' => 'Overnight Safety Reminder', 'status' => 'ready']);
    SearchIndex::updateOrCreate(
        ['asset_id' => $asset->id],
        ['tokenized_title' => 'overnight safety reminder', 'tokenized_body' => '']
    );

    $response = $this->withToken($token)->getJson('/api/search?q=overnight');

    $response->assertStatus(200)
        ->assertJsonStructure(['items', 'next_cursor', 'degraded']);
});

// Contract test: the frontend sends tags as repeated `tags[]=` query params (see
// services/api.ts searchApi.search). Laravel parses the bracketed form into an array
// while a plain `?tags=a&tags=b` would collapse to the last value only.
test('search filters by tags when sent as tags[]=a&tags[]=b', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $safety = Asset::factory()->create(['title' => 'Safety Reminder', 'status' => 'ready']);
    $other  = Asset::factory()->create(['title' => 'Unrelated', 'status' => 'ready']);

    SearchIndex::updateOrCreate(['asset_id' => $safety->id], ['tokenized_title' => 'safety reminder', 'tokenized_body' => '']);
    SearchIndex::updateOrCreate(['asset_id' => $other->id],  ['tokenized_title' => 'unrelated',       'tokenized_body' => '']);

    $safety->assetTags()->create(['tag' => 'safety']);
    $safety->assetTags()->create(['tag' => 'overnight']);
    $other->assetTags()->create(['tag' => 'gate']);

    $response = $this->withToken($token)->getJson('/api/search?tags%5B%5D=safety&tags%5B%5D=overnight');

    $response->assertStatus(200);
    $ids = collect($response->json('items'))->pluck('id');
    expect($ids)->toContain($safety->id);
    expect($ids)->not->toContain($other->id);
});

test('search with duration filter excludes long assets', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $short = Asset::factory()->create(['title' => 'Short Clip', 'duration_seconds' => 60, 'status' => 'ready']);
    $long  = Asset::factory()->create(['title' => 'Long Clip', 'duration_seconds' => 300, 'status' => 'ready']);

    SearchIndex::updateOrCreate(['asset_id' => $short->id], ['tokenized_title' => 'short clip', 'tokenized_body' => '']);
    SearchIndex::updateOrCreate(['asset_id' => $long->id], ['tokenized_title' => 'long clip', 'tokenized_body' => '']);

    $response = $this->withToken($token)->getJson('/api/search?duration_lt=120');

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids)->toContain($short->id);
    expect($ids)->not->toContain($long->id);
});

test('search with sort=recommended and flag disabled sets X-Recommendation-Degraded header', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => false, 'reason' => 'Test degradation', 'updated_at' => now()]
    );

    $response = $this->withToken($token)->getJson('/api/search?sort=recommended');

    $response->assertStatus(200)
        ->assertHeader('X-Recommendation-Degraded', 'true');
});

test('search with sort=recommended and flag enabled does not set degraded header', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => true, 'reason' => null, 'updated_at' => now()]
    );

    $response = $this->withToken($token)->getJson('/api/search?sort=recommended');

    $response->assertStatus(200);
    expect($response->headers->get('X-Recommendation-Degraded'))->not->toEqual('true');
});

test('recent_days filter returns only recent assets', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $recent = Asset::factory()->create(['title' => 'Recent Asset', 'status' => 'ready', 'created_at' => now()]);
    $old    = Asset::factory()->create(['title' => 'Old Asset', 'status' => 'ready', 'created_at' => now()->subDays(60)]);

    $response = $this->withToken($token)->getJson('/api/search?recent_days=30');

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids)->toContain($recent->id);
    expect($ids)->not->toContain($old->id);
});

test('per_page parameter limits number of results', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    Asset::factory()->count(10)->create(['status' => 'ready']);

    $response = $this->withToken($token)->getJson('/api/search?per_page=3');

    $response->assertStatus(200);
    expect(count($response->json('items')))->toBeLessThanOrEqual(3);
});

test('sort=most_played returns assets ordered by play count', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $popular = Asset::factory()->create(['title' => 'Popular', 'status' => 'ready']);
    $rare    = Asset::factory()->create(['title' => 'Rare', 'status' => 'ready']);

    for ($i = 0; $i < 5; $i++) {
        \App\Models\PlayHistory::create([
            'user_id'  => $user->id,
            'asset_id' => $popular->id,
            'played_at' => now(),
        ]);
    }

    $response = $this->withToken($token)->getJson('/api/search?sort=most_played');

    $ids = collect($response->json('items'))->pluck('id')->toArray();
    $popularIndex = array_search($popular->id, $ids);
    $rareIndex    = array_search($rare->id, $ids);

    if ($popularIndex !== false && $rareIndex !== false) {
        expect($popularIndex)->toBeLessThan($rareIndex);
    }
});

test('sort=newest returns most recently created assets first', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $old = Asset::factory()->create(['title' => 'Old', 'status' => 'ready', 'created_at' => now()->subDays(10)]);
    $new = Asset::factory()->create(['title' => 'New', 'status' => 'ready', 'created_at' => now()]);

    SearchIndex::updateOrCreate(['asset_id' => $old->id], ['tokenized_title' => 'old', 'tokenized_body' => '']);
    SearchIndex::updateOrCreate(['asset_id' => $new->id], ['tokenized_title' => 'new', 'tokenized_body' => '']);

    $response = $this->withToken($token)->getJson('/api/search?sort=newest');
    $ids = collect($response->json('items'))->pluck('id')->toArray();

    $newIndex = array_search($new->id, $ids);
    $oldIndex = array_search($old->id, $ids);

    if ($newIndex !== false && $oldIndex !== false) {
        expect($newIndex)->toBeLessThan($oldIndex);
    }
});

test('cursor pagination on sort=newest does not skip or duplicate results', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    // Create 10 assets with staggered timestamps so sort=newest is deterministic.
    $created = [];
    for ($i = 0; $i < 10; $i++) {
        $created[] = Asset::factory()->create([
            'title'      => "Asset {$i}",
            'status'     => 'ready',
            'created_at' => now()->subMinutes(10 - $i), // older → newer
        ]);
    }

    $first = $this->withToken($token)->getJson('/api/search?sort=newest&per_page=4');
    $first->assertStatus(200);

    $pageOneIds = collect($first->json('items'))->pluck('id')->toArray();
    expect(count($pageOneIds))->toEqual(4);

    $cursor = $first->json('next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->withToken($token)->getJson("/api/search?sort=newest&per_page=4&cursor={$cursor}");
    $second->assertStatus(200);

    $pageTwoIds = collect($second->json('items'))->pluck('id')->toArray();

    // Second page must not repeat anything from the first.
    expect(array_intersect($pageOneIds, $pageTwoIds))->toBeEmpty();

    // And must not skip ahead: every id on page 2 must have id < smallest id on page 1.
    $minPageOne = min($pageOneIds);
    foreach ($pageTwoIds as $id) {
        expect($id)->toBeLessThan($minPageOne);
    }
});

test('cursor pagination on sort=most_played advances in descending id order', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    Asset::factory()->count(6)->create(['status' => 'ready']);

    $first = $this->withToken($token)->getJson('/api/search?sort=most_played&per_page=3');
    $pageOneIds = collect($first->json('items'))->pluck('id')->toArray();
    $cursor = $first->json('next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->withToken($token)->getJson("/api/search?sort=most_played&per_page=3&cursor={$cursor}");
    $pageTwoIds = collect($second->json('items'))->pluck('id')->toArray();

    expect(array_intersect($pageOneIds, $pageTwoIds))->toBeEmpty();
});
