<?php

use App\Models\FeatureFlag;
use App\Models\User;

test('monitoring status returns expected structure', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => true, 'reason' => null, 'updated_at' => now()]
    );

    $response = $this->withToken($token)->getJson('/api/monitoring/status');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'api'    => ['p95_ms_5m', 'error_rate_5m'],
            'queues',
            'storage' => ['media_volume_free_bytes', 'media_volume_used_pct'],
            'devices' => ['online', 'offline', 'dedup_rate_1h'],
            'feature_flags',
        ]);
});

test('admin can reset the recommended flag', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => false, 'reason' => 'Circuit tripped', 'updated_at' => now()]
    );

    $response = $this->withToken($token)
        ->postJson('/api/monitoring/feature-flags/recommended/reset');

    $response->assertStatus(200);

    $flag = FeatureFlag::find('recommended_enabled');
    expect($flag->enabled)->toBeTrue();
});

test('non-admin cannot access monitoring status', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/monitoring/status')
        ->assertStatus(403);
});

test('monitoring feature_flags shows recommended_enabled state', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => false, 'reason' => 'High latency', 'updated_at' => now()]
    );

    $response = $this->withToken($token)->getJson('/api/monitoring/status');

    $flags = $response->json('feature_flags');
    expect($flags['recommended_enabled']['enabled'])->toBeFalse();
    expect($flags['recommended_enabled']['reason'])->toEqual('High latency');
});
