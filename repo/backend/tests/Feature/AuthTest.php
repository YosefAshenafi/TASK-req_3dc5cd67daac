<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'user',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user' => ['id', 'name', 'username', 'email', 'role']]);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'user',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_login_validation_422_on_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_login_frozen_user_returns_401(): void
    {
        User::factory()->frozen()->create([
            'username' => 'frozenuser',
            'email' => 'frozen@test.local',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'frozenuser',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_blacklisted_user_returns_401(): void
    {
        User::factory()->blacklisted()->create([
            'username' => 'blacklisteduser',
            'email' => 'blacklisted@test.local',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'blacklisteduser',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure(['user' => ['id', 'name', 'username', 'role']]);
    }

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
    }

    public function test_password_not_in_me_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/auth/me');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    public function test_login_unknown_username_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'nonexistent_user',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401);
    }
}
