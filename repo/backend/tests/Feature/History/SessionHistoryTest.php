<?php

use App\Models\Asset;
use App\Models\PlayHistory;
use App\Models\User;

test('POST /assets/{id}/play accepts and persists session_id', function () {
    $user  = User::factory()->create();
    $asset = Asset::factory()->create();
    $token = $user->createToken('session-test')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/assets/{$asset->id}/play", [
            'session_id' => 'sess-abc-123',
            'context'    => 'library-shuffle',
        ])
        ->assertStatus(202)
        ->assertJsonPath('session_id', 'sess-abc-123')
        ->assertJsonPath('context', 'library-shuffle');

    $stored = PlayHistory::where('user_id', $user->id)->first();
    expect($stored->session_id)->toBe('sess-abc-123');
    expect($stored->context)->toBe('library-shuffle');
});

test('GET /history returns session_id on each entry', function () {
    $user  = User::factory()->create();
    $asset = Asset::factory()->create();
    $token = $user->createToken('session-test')->plainTextToken;

    PlayHistory::create([
        'user_id'    => $user->id,
        'asset_id'   => $asset->id,
        'played_at'  => now(),
        'session_id' => 'sess-list-1',
        'context'    => 'favorites',
    ]);

    $response = $this->withToken($token)->getJson('/api/history');

    $response->assertStatus(200);
    $items = $response->json('items');
    expect($items)->not->toBeEmpty();
    expect($items[0]['session_id'])->toBe('sess-list-1');
    expect($items[0]['context'])->toBe('favorites');
});

test('GET /history/sessions groups plays by session_id', function () {
    $user  = User::factory()->create();
    $assetA = Asset::factory()->create();
    $assetB = Asset::factory()->create();
    $assetC = Asset::factory()->create();
    $token  = $user->createToken('session-test')->plainTextToken;

    // Session 1: two plays
    PlayHistory::create([
        'user_id'    => $user->id,
        'asset_id'   => $assetA->id,
        'played_at'  => now()->subMinutes(10),
        'session_id' => 'sess-1',
        'context'    => 'browse',
    ]);
    PlayHistory::create([
        'user_id'    => $user->id,
        'asset_id'   => $assetB->id,
        'played_at'  => now()->subMinutes(5),
        'session_id' => 'sess-1',
        'context'    => 'browse',
    ]);
    // Session 2: one play
    PlayHistory::create([
        'user_id'    => $user->id,
        'asset_id'   => $assetC->id,
        'played_at'  => now()->subMinutes(1),
        'session_id' => 'sess-2',
        'context'    => 'search',
    ]);

    $response = $this->withToken($token)->getJson('/api/history/sessions');
    $response->assertStatus(200);

    $sessions = $response->json('sessions');
    expect($sessions)->toHaveCount(2);

    $byId = collect($sessions)->keyBy('session_id');
    expect($byId['sess-1']['play_count'])->toBe(2);
    expect($byId['sess-2']['play_count'])->toBe(1);

    // Newest session first
    expect($sessions[0]['session_id'])->toBe('sess-2');
});

test('GET /history/sessions groups null session_id into unassigned bucket', function () {
    $user  = User::factory()->create();
    $asset = Asset::factory()->create();
    $token = $user->createToken('session-test')->plainTextToken;

    PlayHistory::create([
        'user_id'    => $user->id,
        'asset_id'   => $asset->id,
        'played_at'  => now(),
        'session_id' => null,
    ]);

    $response = $this->withToken($token)->getJson('/api/history/sessions');
    $response->assertStatus(200);

    $sessions = $response->json('sessions');
    expect($sessions)->toHaveCount(1);
    expect($sessions[0]['session_id'])->toBeNull();
    expect($sessions[0]['play_count'])->toBe(1);
});

test('GET /history/sessions does not leak cross-user sessions', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $asset = Asset::factory()->create();
    $tokenA = $userA->createToken('session-test')->plainTextToken;

    PlayHistory::create([
        'user_id'    => $userB->id,
        'asset_id'   => $asset->id,
        'played_at'  => now(),
        'session_id' => 'secret-session-b',
    ]);

    $response = $this->withToken($tokenA)->getJson('/api/history/sessions');
    $response->assertStatus(200);

    $ids = collect($response->json('sessions'))->pluck('session_id')->toArray();
    expect($ids)->not->toContain('secret-session-b');
});

test('GET /now-playing exposes session_id and current_session_id', function () {
    $user  = User::factory()->create();
    $asset = Asset::factory()->create();
    $token = $user->createToken('session-test')->plainTextToken;

    PlayHistory::create([
        'user_id'    => $user->id,
        'asset_id'   => $asset->id,
        'played_at'  => now(),
        'session_id' => 'sess-now',
    ]);

    $response = $this->withToken($token)->getJson('/api/now-playing');
    $response->assertStatus(200)
        ->assertJsonPath('current_session_id', 'sess-now')
        ->assertJsonPath('current.session_id', 'sess-now');
});
