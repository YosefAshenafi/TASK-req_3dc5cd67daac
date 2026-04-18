<?php

use App\Models\User;

test('admin can get a user by id', function () {
    $admin = User::factory()->admin()->create();
    $user  = User::factory()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson("/api/users/{$user->id}")
        ->assertStatus(200)
        ->assertJsonStructure(['id', 'username', 'role', 'created_at'])
        ->assertJsonPath('id', $user->id);
});

test('admin can update a user via PUT', function () {
    $admin = User::factory()->admin()->create();
    $user  = User::factory()->create(['role' => 'user']);
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)->putJson("/api/users/{$user->id}", [
        'role' => 'technician',
    ])
        ->assertStatus(200)
        ->assertJsonPath('role', 'technician');

    expect($user->fresh()->role)->toBe('technician');
});

test('admin can update a user via PATCH', function () {
    $admin = User::factory()->admin()->create();
    $user  = User::factory()->create(['role' => 'user']);
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)->patchJson("/api/users/{$user->id}", [
        'role' => 'admin',
    ])
        ->assertStatus(200)
        ->assertJsonPath('role', 'admin');
});

test('non-admin cannot get or update a user', function () {
    $user   = User::factory()->create();
    $target = User::factory()->create();
    $token  = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson("/api/users/{$target->id}")->assertStatus(403);
    $this->withToken($token)->putJson("/api/users/{$target->id}", ['role' => 'admin'])->assertStatus(403);
    $this->withToken($token)->patchJson("/api/users/{$target->id}", ['role' => 'admin'])->assertStatus(403);
});
