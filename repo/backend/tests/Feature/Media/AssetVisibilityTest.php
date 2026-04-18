<?php

use App\Models\Asset;
use App\Models\User;

test('non-admin user cannot read asset in processing status', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;

    $asset = Asset::factory()->create(['status' => 'processing']);

    $this->withToken($token)->getJson("/api/assets/{$asset->id}")
        ->assertStatus(404);
});

test('non-admin user cannot read asset in failed status', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;

    $asset = Asset::factory()->create(['status' => 'failed']);

    $this->withToken($token)->getJson("/api/assets/{$asset->id}")
        ->assertStatus(404);
});

test('non-admin user can read ready assets', function () {
    $user  = User::factory()->create(['role' => 'user']);
    $token = $user->createToken('test')->plainTextToken;

    $asset = Asset::factory()->create(['status' => 'ready']);

    $this->withToken($token)->getJson("/api/assets/{$asset->id}")
        ->assertStatus(200)
        ->assertJsonFragment(['id' => $asset->id]);
});

test('admin can read assets of any status', function () {
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $processing = Asset::factory()->create(['status' => 'processing']);
    $failed     = Asset::factory()->create(['status' => 'failed']);

    $this->withToken($token)->getJson("/api/assets/{$processing->id}")->assertStatus(200);
    $this->withToken($token)->getJson("/api/assets/{$failed->id}")->assertStatus(200);
});

test('technician cannot read non-ready assets', function () {
    $tech  = User::factory()->create(['role' => 'technician']);
    $token = $tech->createToken('test')->plainTextToken;

    $asset = Asset::factory()->create(['status' => 'processing']);

    // Technicians are not the approval gate — only admins see unreviewed media.
    $this->withToken($token)->getJson("/api/assets/{$asset->id}")
        ->assertStatus(404);
});
