<?php

use App\Models\User;

// Freeze/blacklist must apply to already-issued tokens, not just login. A user who
// has a valid token and then gets frozen mid-session should be rejected on the
// next API request — the `EnforceAccountStatus` middleware is what enforces this.
//
// These tests assert the full response contract (status + reason_code + message
// + frozen_until ISO timestamp) so the SPA can show a specific state-aware toast
// instead of a generic "HTTP 401" fallback.

test('frozen user hits 423 with reason_code=account_frozen and a non-empty message on any authenticated endpoint', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    // Mutate after the token was issued.
    $user->frozen_until = now()->addHours(24);
    $user->save();

    $response = $this->withToken($token)->getJson('/api/auth/me');

    $response->assertStatus(423)
        ->assertJsonStructure(['message', 'frozen_until', 'reason_code'])
        ->assertJsonPath('reason_code', 'account_frozen')
        ->assertJsonPath('message', 'Account is temporarily frozen.');

    // frozen_until must be a parseable ISO-8601 timestamp in the future — the
    // SPA shows a "try again after X" toast keyed off this value.
    $frozenUntil = \Carbon\Carbon::parse($response->json('frozen_until'));
    expect($frozenUntil->isFuture())->toBeTrue();
});

test('blacklisted user hits 401 with reason_code=account_blacklisted and a message', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $user->blacklisted_at = now();
    $user->save();

    $response = $this->withToken($token)->getJson('/api/auth/me');

    $response->assertStatus(401)
        ->assertJsonStructure(['message', 'reason_code'])
        ->assertJsonPath('reason_code', 'account_blacklisted')
        ->assertJsonPath('message', 'This account has been disabled.');

    // Blacklist must NOT expose frozen_until (which would imply recoverable state).
    expect($response->json('frozen_until'))->toBeNull();
});

test('frozen user can still log out cleanly with a 204', function () {
    $user  = User::factory()->frozen()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/auth/logout');
    $response->assertStatus(204);
    // 204 means empty body. If the middleware accidentally intercepts logout,
    // we'd see a JSON body instead.
    expect($response->getContent())->toBe('');
});

test('active user is not affected by the status middleware — /me returns 200 with profile', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/auth/me');
    $response->assertStatus(200)
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('frozen_until', null)
        ->assertJsonPath('blacklisted_at', null);
});

test('frozen user is rejected uniformly on search/favorites/playlists with the same 423 shape', function () {
    $user  = User::factory()->frozen()->create();
    $token = $user->createToken('test')->plainTextToken;

    foreach (['/api/search', '/api/favorites', '/api/playlists'] as $endpoint) {
        $response = $this->withToken($token)->getJson($endpoint);

        $response->assertStatus(423)
            ->assertJsonStructure(['message', 'frozen_until', 'reason_code'])
            ->assertJsonPath('reason_code', 'account_frozen');

        // Denial is uniform across endpoints — no endpoint leaks data past
        // the status middleware (e.g., playlists[] or items[]).
        expect($response->json('data'))->toBeNull();
        expect($response->json('items'))->toBeNull();
    }
});

test('blacklisted user is rejected uniformly on search/favorites/playlists with 401 and reason_code', function () {
    // Blacklist is a HARD 401 — explicitly a different status than frozen (423).
    // Tested here across multiple endpoints to ensure the middleware ordering
    // doesn't accidentally let a blacklisted user reach the controller.
    $user  = User::factory()->blacklisted()->create();
    $token = $user->createToken('test')->plainTextToken;

    foreach (['/api/search', '/api/favorites', '/api/playlists', '/api/auth/me'] as $endpoint) {
        $response = $this->withToken($token)->getJson($endpoint);

        $response->assertStatus(401)
            ->assertJsonStructure(['message', 'reason_code'])
            ->assertJsonPath('reason_code', 'account_blacklisted');
    }
});

test('frozen-until in the past is treated as active (thaw scenario)', function () {
    // If the cron/admin reset has already run, frozen_until pointing to the past
    // must not keep the user frozen — the middleware must treat it as "not frozen".
    $user = User::factory()->create();
    $user->frozen_until = now()->subMinutes(1);
    $user->save();

    $token = $user->createToken('test')->plainTextToken;
    $response = $this->withToken($token)->getJson('/api/auth/me');

    $response->assertStatus(200);
    // frozen_until is still reported on the profile so the UI can show "last
    // thawed at X" if needed, but it no longer blocks.
    expect($response->json('frozen_until'))->not->toBeNull();
});

test('malformed Bearer token returns 401 without engaging status middleware', function () {
    // Garbage token → Sanctum rejects → 401. The status middleware runs AFTER
    // auth resolution so a bogus token doesn't get a 423/401 with reason_code.
    $response = $this->withToken('bogus-token-value')->getJson('/api/auth/me');
    $response->assertStatus(401);
    expect($response->json('reason_code'))->toBeNull();
});

test('missing Authorization header returns 401 with a JSON message on a protected endpoint', function () {
    $response = $this->getJson('/api/auth/me');
    $response->assertStatus(401);
    expect($response->json())->toBeArray();
    expect($response->json('message'))->toBeString()->not->toBeEmpty();
});
