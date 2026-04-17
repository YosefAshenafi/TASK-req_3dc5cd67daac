<?php

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    // Clear all relevant rate limiters
    User::all()->each(function ($u) {
        RateLimiter::clear('playlist_share:' . $u->id);
    });
});

test('6th share code request in an hour returns 429 with retry_after', function () {
    $user     = User::factory()->create();
    $token    = $user->createToken('test')->plainTextToken;
    $playlist = Playlist::factory()->create(['owner_id' => $user->id]);

    for ($i = 0; $i < 5; $i++) {
        $this->withToken($token)->postJson("/api/playlists/{$playlist->id}/share");
    }

    $response = $this->withToken($token)->postJson("/api/playlists/{$playlist->id}/share");

    $response->assertStatus(429)
        ->assertJsonStructure(['message', 'retry_after']);
});

test('login rate limit applies after 5 failed attempts', function () {
    RateLimiter::clear('login_attempts:ratelimituser');
    User::factory()->create(['username' => 'ratelimituser']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'username' => 'ratelimituser',
            'password' => 'wrong',
        ]);
    }

    $response = $this->postJson('/api/auth/login', [
        'username' => 'ratelimituser',
        'password' => 'wrong',
    ]);

    $response->assertStatus(429)
        ->assertJsonStructure(['message', 'retry_after']);
});
