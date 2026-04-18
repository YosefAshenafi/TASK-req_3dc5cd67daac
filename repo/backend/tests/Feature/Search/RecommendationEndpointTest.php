<?php

use App\Models\Asset;
use App\Models\FeatureFlag;
use App\Models\PlayHistory;
use App\Models\RecommendationCandidate;
use App\Models\User;

test('GET /recommendations returns a degraded contract and falls back to most_played when the feature flag is off', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('rec-test')->plainTextToken;

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => false, 'reason' => 'test: circuit breaker tripped', 'updated_at' => now()]
    );

    // Two ready assets with different play counts — fallback should rank by play count.
    $hot  = Asset::factory()->create(['status' => 'ready', 'title' => 'Hot']);
    $cold = Asset::factory()->create(['status' => 'ready', 'title' => 'Cold']);
    $other = User::factory()->create();
    for ($i = 0; $i < 5; $i++) {
        PlayHistory::create([
            'user_id'   => $other->id,
            'asset_id'  => $hot->id,
            'played_at' => now()->subMinutes($i),
        ]);
    }
    PlayHistory::create([
        'user_id'   => $other->id,
        'asset_id'  => $cold->id,
        'played_at' => now()->subHour(),
    ]);

    $response = $this->withToken($token)->getJson('/api/recommendations');

    $response->assertStatus(200)
        ->assertJsonPath('degraded', true)
        ->assertJsonPath('fallback', 'most_played')
        ->assertHeader('X-Recommendation-Degraded', 'true');

    $items = $response->json('items');
    expect($items)->not->toBeEmpty();
    // Most-played first
    expect($items[0]['asset_id'])->toBe($hot->id);
});

test('GET /recommendations returns items with degraded=false when the feature flag is on', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('rec-test')->plainTextToken;

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => true, 'reason' => null, 'updated_at' => now()]
    );

    $asset = Asset::factory()->create(['status' => 'ready']);
    RecommendationCandidate::create([
        'user_id'          => $user->id,
        'asset_id'         => $asset->id,
        'score'            => 0.91,
        'reason_tags_json' => ['jazz', 'focus'],
        'refreshed_at'     => now(),
    ]);

    $response = $this->withToken($token)->getJson('/api/recommendations');

    $response->assertStatus(200)
        ->assertJsonPath('degraded', false)
        ->assertJsonPath('fallback', null);

    $items = $response->json('items');
    expect($items)->not->toBeEmpty();
    expect($items[0]['asset_id'])->toBe($asset->id);
    expect($items[0]['reason_tags'])->toContain('jazz');
});

test('GET /recommendations wraps items inside the documented response shape', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('rec-test')->plainTextToken;

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => true, 'reason' => null, 'updated_at' => now()]
    );

    $response = $this->withToken($token)->getJson('/api/recommendations');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'items',
            'degraded',
            'fallback',
        ]);
});
