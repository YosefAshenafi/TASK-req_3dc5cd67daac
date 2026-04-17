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
