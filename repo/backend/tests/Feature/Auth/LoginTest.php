<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    // The production key includes the request IP suffix; clearing both the
    // bare and the 127.0.0.1 variant defensively in case RateLimiter resolves
    // either form during the test run.
    foreach (['testuser', 'frozen', 'blacklisted', 'admin', 'deleted_user', 'target_user'] as $name) {
        RateLimiter::clear("login_attempts:{$name}");
        RateLimiter::clear("login_attempts:{$name}:127.0.0.1");
    }
});

test('login success returns token, CSRF cookie placeholder, and user resource', function () {
    $user = User::factory()->create(['username' => 'testuser']);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'testuser',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['user' => ['id', 'username', 'role'], 'token', 'csrf_token'])
        ->assertJsonPath('user.username', 'testuser')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.role', $user->role);

    // Token is a real PAT that lives in personal_access_tokens; pulling it back
    // out by id ensures the response is not merely echoing a plaintext string.
    $rawToken   = $response->json('token');
    $tokenParts = explode('|', $rawToken, 2);
    expect($tokenParts)->toHaveCount(2);
    expect(PersonalAccessToken::find((int) $tokenParts[0]))->not->toBeNull();
});

test('login fails with wrong password, returns 401 with message and attempts_remaining', function () {
    User::factory()->create(['username' => 'testuser']);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'testuser',
        'password' => 'wrong',
    ]);

    $response->assertStatus(401)
        ->assertJsonStructure(['message', 'attempts_remaining'])
        ->assertJsonPath('attempts_remaining', 4)
        ->assertJsonPath('message', 'Invalid credentials.');

    // On a 401 the server must NOT leak a token or user object.
    expect($response->json('token'))->toBeNull();
    expect($response->json('user'))->toBeNull();
});

test('login fails with unknown username returns the same generic 401 shape (no user enumeration)', function () {
    $response = $this->postJson('/api/auth/login', [
        'username' => 'nosuchuser_here',
        'password' => 'anything',
    ]);

    $response->assertStatus(401)
        ->assertJsonStructure(['message', 'attempts_remaining'])
        ->assertJsonPath('message', 'Invalid credentials.');
});

test('login is locked after 5 failed attempts and returns 429 with retry_after seconds', function () {
    User::factory()->create(['username' => 'testuser']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'username' => 'testuser',
            'password' => 'wrong',
        ]);
    }

    $response = $this->postJson('/api/auth/login', [
        'username' => 'testuser',
        'password' => 'wrong',
    ]);

    $response->assertStatus(429)
        ->assertJsonStructure(['message', 'retry_after'])
        ->assertJsonPath('message', 'Too many login attempts.');

    // retry_after must be a positive integer of seconds; 15 minutes → at most 900.
    expect((int) $response->json('retry_after'))->toBeGreaterThan(0);
    expect((int) $response->json('retry_after'))->toBeLessThanOrEqual(15 * 60);
});

test('frozen user returns 423 with frozen_until ISO-8601 timestamp', function () {
    $frozen = User::factory()->frozen()->create(['username' => 'frozen']);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'frozen',
        'password' => 'password',
    ]);

    $response->assertStatus(423)
        ->assertJsonStructure(['message', 'frozen_until'])
        ->assertJsonPath('message', 'Account is temporarily frozen.');

    // 423 specifically means locked-but-legitimate (different from wrong
    // credentials 401 and rate-limited 429). No token is issued.
    expect($response->json('token'))->toBeNull();
    expect($response->json('frozen_until'))->toBe($frozen->frozen_until->toIso8601String());
});

test('blacklisted user returns a generic 401 with no frozen_until or token leak', function () {
    User::factory()->blacklisted()->create(['username' => 'blacklisted']);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'blacklisted',
        'password' => 'password',
    ]);

    $response->assertStatus(401)
        ->assertJsonMissing(['frozen_until'])
        ->assertJsonPath('message', 'This account has been disabled.');

    expect($response->json('token'))->toBeNull();
});

test('login with missing username returns 422 with errors.username', function () {
    $response = $this->postJson('/api/auth/login', [
        'password' => 'password',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['username']);
});

test('login with missing password returns 422 with errors.password', function () {
    $response = $this->postJson('/api/auth/login', [
        'username' => 'testuser',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['password']);
});

test('login with empty JSON body returns 422 with both required fields', function () {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertStatus(422)->assertJsonValidationErrors(['username', 'password']);
});

test('authenticated user can get me with full profile shape', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'username', 'role', 'frozen_until', 'blacklisted_at', 'favorites_count', 'created_at'])
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('username', $user->username)
        ->assertJsonPath('role', $user->role)
        ->assertJsonPath('frozen_until', null)
        ->assertJsonPath('blacklisted_at', null)
        ->assertJsonPath('favorites_count', 0);
});

test('unauthenticated request to me returns 401 with JSON message', function () {
    $response = $this->getJson('/api/auth/me');
    $response->assertStatus(401);
    expect($response->json('message'))->toBeString()->not->toBeEmpty();
});

test('logout returns 204 and deletes the current token from the database', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/auth/logout');
    $response->assertStatus(204);
    // 204 means empty body — verify that explicitly.
    expect($response->getContent())->toBe('');

    $tokenId = explode('|', $token)[0];
    expect(PersonalAccessToken::find($tokenId))->toBeNull();
});

test('regular user cannot access admin users endpoint and gets 403 with a JSON message', function () {
    $user = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;

    // The pre-existing version only asserted the status code. Admin-only denial
    // *also* needs to return a readable body so the SPA can show a toast
    // instead of falling back to "HTTP 403".
    $response = $this->withToken($token)->getJson('/api/users');

    $response->assertStatus(403);
    expect($response->json())->toBeArray();
    expect($response->json('message'))->toBeString()->not->toBeEmpty();
});

test('technician cannot access admin users endpoint either', function () {
    $tech  = User::factory()->technician()->create();
    $token = $tech->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/users');
    $response->assertStatus(403);
});

test('admin can access admin users endpoint and receives a paginated list', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/users');

    $response->assertStatus(200)
        ->assertJsonStructure(['items']);
    // The admin account itself must be in the result since it was just created.
    $usernames = collect($response->json('items'))->pluck('username');
    expect($usernames)->toContain($admin->username);
});

test('soft-deleted user cannot log in (treated as invalid credentials)', function () {
    $user = User::factory()->create(['username' => 'deleted_user']);
    $user->delete();

    $response = $this->postJson('/api/auth/login', [
        'username' => 'deleted_user',
        'password' => 'password',
    ]);

    $response->assertStatus(401)
        ->assertJsonStructure(['message', 'attempts_remaining'])
        ->assertJsonPath('message', 'Invalid credentials.');
});

test('blacklisted user tokens are deleted from database at moment of blacklisting', function () {
    $admin  = User::factory()->admin()->create();
    $target = User::factory()->create(['username' => 'target_user']);
    $adminToken  = $admin->createToken('test')->plainTextToken;
    $targetToken = $target->createToken('active')->plainTextToken;

    $targetTokenId = explode('|', $targetToken)[0];

    // Verify token exists before blacklisting
    expect(PersonalAccessToken::find($targetTokenId))->not->toBeNull();

    // Blacklist the target — 200 with the updated User resource.
    $response = $this->withToken($adminToken)->patchJson("/api/users/{$target->id}/blacklist");
    $response->assertStatus(200)
        ->assertJsonPath('id', $target->id)
        ->assertJsonPath('username', 'target_user');
    expect($response->json('blacklisted_at'))->not->toBeNull();

    // All target tokens should be deleted from the database
    expect(PersonalAccessToken::find($targetTokenId))->toBeNull();

    // Admin token is unaffected.
    $adminTokenId = explode('|', $adminToken)[0];
    expect(PersonalAccessToken::find($adminTokenId))->not->toBeNull();
});
