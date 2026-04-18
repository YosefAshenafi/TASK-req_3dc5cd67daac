<?php

use App\Models\User;

test('admin can create a user', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/users', [
        'username' => 'newuser',
        'password' => 'password123',
        'role'     => 'user',
        'email'    => 'new@site.local',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('username', 'newuser');
});

test('admin can freeze a user', function () {
    $admin = User::factory()->admin()->create();
    $user  = User::factory()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->patchJson("/api/users/{$user->id}/freeze", [
        'duration_hours' => 72,
        'reason'         => 'Policy violation',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['id', 'username', 'role', 'frozen_until']);

    expect($response->json('frozen_until'))->not->toBeNull();

    $user->refresh();
    expect($user->frozen_until)->not->toBeNull();
});

test('admin can unfreeze a user', function () {
    $admin = User::factory()->admin()->create();
    $user  = User::factory()->frozen()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->patchJson("/api/users/{$user->id}/unfreeze");

    $response->assertStatus(200);
    $user->refresh();
    expect($user->frozen_until)->toBeNull();
});

test('admin can blacklist a user', function () {
    $admin = User::factory()->admin()->create();
    $user  = User::factory()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->patchJson("/api/users/{$user->id}/blacklist", [
        'reason' => 'Repeat abuse',
    ]);

    $response->assertStatus(200);
    $user->refresh();
    expect($user->blacklisted_at)->not->toBeNull();
});

test('admin can soft-delete a user', function () {
    $admin = User::factory()->admin()->create();
    $user  = User::factory()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)->deleteJson("/api/users/{$user->id}")
        ->assertStatus(204);

    $user->refresh();
    expect($user->deleted_at)->not->toBeNull();
});

test('non-admin cannot manage users', function () {
    $user = User::factory()->create(['role' => 'user']);
    $target = User::factory()->create();
    $token  = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->patchJson("/api/users/{$target->id}/freeze", [
        'duration_hours' => 24,
    ])->assertStatus(403);
});
