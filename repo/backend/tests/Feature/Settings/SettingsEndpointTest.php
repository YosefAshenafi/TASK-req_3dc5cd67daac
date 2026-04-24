<?php

use App\Models\AppSetting;
use App\Models\User;

test('GET /api/settings returns defaults when no overrides exist', function () {
    $response = $this->getJson('/api/settings');

    $response->assertStatus(200)
        ->assertJsonStructure(['site_name', 'site_tagline', 'available_tags'])
        ->assertJsonPath('site_name', 'SmartPark')
        ->assertJsonPath('site_tagline', 'Find and discover media assets');

    expect($response->json('available_tags'))->toBeArray();
});

test('GET /api/settings returns stored values when present', function () {
    AppSetting::setValue('site_name', 'CustomPark');
    AppSetting::setValue('site_tagline', 'Custom tagline');
    AppSetting::setValue('available_tags', ['Alpha', 'Beta']);

    $response = $this->getJson('/api/settings');

    $response->assertStatus(200)
        ->assertJsonPath('site_name', 'CustomPark')
        ->assertJsonPath('site_tagline', 'Custom tagline')
        ->assertJsonPath('available_tags', ['Alpha', 'Beta']);
});

test('PUT /api/settings rejects unauthenticated requests', function () {
    $this->putJson('/api/settings', ['site_name' => 'Nope'])
        ->assertStatus(401);

    expect(AppSetting::where('key', 'site_name')->exists())->toBeFalse();
});

test('PUT /api/settings rejects non-admin users with 403', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->putJson('/api/settings', [
        'site_name' => 'Blocked',
    ])->assertStatus(403);

    expect(AppSetting::where('key', 'site_name')->exists())->toBeFalse();
});

test('PUT /api/settings rejects technician users with 403', function () {
    $tech  = User::factory()->technician()->create();
    $token = $tech->createToken('test')->plainTextToken;

    $this->withToken($token)->putJson('/api/settings', [
        'site_name' => 'Blocked',
    ])->assertStatus(403);

    expect(AppSetting::where('key', 'site_name')->exists())->toBeFalse();
});

test('PUT /api/settings persists partial updates and returns the resolved settings', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/settings', [
        'site_name'      => 'NewPark',
        'available_tags' => ['Safety', 'Overnight', 'VIP'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['site_name', 'site_tagline', 'available_tags'])
        ->assertJsonPath('site_name', 'NewPark')
        ->assertJsonPath('available_tags', ['Safety', 'Overnight', 'VIP']);

    // site_tagline was not sent; response must fall back to the default.
    expect($response->json('site_tagline'))->toBe('Find and discover media assets');

    expect(AppSetting::getValue('site_name'))->toBe('NewPark');
    $storedTags = AppSetting::getValue('available_tags');
    $tags       = is_array($storedTags) ? $storedTags : json_decode($storedTags, true);
    expect($tags)->toBe(['Safety', 'Overnight', 'VIP']);
});

test('PUT /api/settings validates field length and type', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)->putJson('/api/settings', [
        'site_name' => str_repeat('x', 81),
    ])->assertStatus(422);

    $this->withToken($token)->putJson('/api/settings', [
        'available_tags' => 'not-an-array',
    ])->assertStatus(422);

    $this->withToken($token)->putJson('/api/settings', [
        'available_tags' => [str_repeat('y', 61)],
    ])->assertStatus(422);

    expect(AppSetting::where('key', 'site_name')->exists())->toBeFalse();
});

test('PUT /api/settings accepts an empty body and returns current resolved settings', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/settings', []);

    $response->assertStatus(200)
        ->assertJsonPath('site_name', 'SmartPark');
});
