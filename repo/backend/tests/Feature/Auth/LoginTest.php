<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('login_attempts:testuser');
    RateLimiter::clear('login_attempts:frozen');
    RateLimiter::clear('login_attempts:blacklisted');
    RateLimiter::clear('login_attempts:admin');
});

test('login success returns token and user', function () {
    $user = User::factory()->create(['username' => 'testuser']);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'testuser',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['user' => ['id', 'username', 'role'], 'token'])
        ->assertJsonPath('user.username', 'testuser');
});

test('login fails with wrong password and decrements attempts', function () {
    User::factory()->create(['username' => 'testuser']);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'testuser',
        'password' => 'wrong',
    ]);

    $response->assertStatus(401)
        ->assertJsonStructure(['message', 'attempts_remaining'])
        ->assertJsonPath('attempts_remaining', 4);
});

test('login is locked after 5 failed attempts', function () {
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
        ->assertJsonStructure(['message', 'retry_after']);
});

test('frozen user returns 423 with frozen_until', function () {
    User::factory()->frozen()->create(['username' => 'frozen']);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'frozen',
        'password' => 'password',
    ]);

    $response->assertStatus(423)
        ->assertJsonStructure(['message', 'frozen_until']);
});

test('blacklisted user returns generic 401', function () {
    User::factory()->blacklisted()->create(['username' => 'blacklisted']);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'blacklisted',
        'password' => 'password',
    ]);

    $response->assertStatus(401)
        ->assertJsonMissing(['frozen_until']);
});

test('authenticated user can get me', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'username', 'role', 'favorites_count']);
});

test('unauthenticated request to me returns 401', function () {
    $this->getJson('/api/auth/me')->assertStatus(401);
});

test('logout returns 204', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertStatus(204);

    // Verify the token was deleted from the database
    $tokenId = explode('|', $token)[0];
    expect(\Laravel\Sanctum\PersonalAccessToken::find($tokenId))->toBeNull();
});

test('regular user cannot access admin endpoints', function () {
    $user = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/users')->assertStatus(403);
});

test('admin can access admin endpoints', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/users')->assertStatus(200);
});

test('soft-deleted user cannot log in', function () {
    $user = User::factory()->create(['username' => 'deleted_user']);
    $user->delete();

    $response = $this->postJson('/api/auth/login', [
        'username' => 'deleted_user',
        'password' => 'password',
    ]);

    $response->assertStatus(401);
});

test('using token after logout returns 401', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertStatus(204);

    $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401);
});
