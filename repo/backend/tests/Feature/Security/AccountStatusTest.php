<?php

use App\Models\User;

// Freeze/blacklist must apply to already-issued tokens, not just login. A user who
// has a valid token and then gets frozen mid-session should be rejected on the
// next API request — the `EnforceAccountStatus` middleware is what enforces this.

test('frozen user cannot call authenticated endpoints even with a valid token', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    // Mutate after the token was issued.
    $user->frozen_until = now()->addHours(24);
    $user->save();

    $response = $this->withToken($token)->getJson('/api/auth/me');

    $response->assertStatus(423)
        ->assertJsonPath('reason_code', 'account_frozen');
});

test('blacklisted user cannot call authenticated endpoints even with a valid token', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $user->blacklisted_at = now();
    $user->save();

    $response = $this->withToken($token)->getJson('/api/auth/me');

    $response->assertStatus(401)
        ->assertJsonPath('reason_code', 'account_blacklisted');
});

test('frozen user can still log out cleanly', function () {
    $user  = User::factory()->frozen()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertStatus(204);
});

test('active user is not affected by the status middleware', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/auth/me')->assertStatus(200);
});

test('frozen user cannot search, favorite, or list playlists', function () {
    $user  = User::factory()->frozen()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/search')->assertStatus(423);
    $this->withToken($token)->getJson('/api/favorites')->assertStatus(423);
    $this->withToken($token)->getJson('/api/playlists')->assertStatus(423);
});
