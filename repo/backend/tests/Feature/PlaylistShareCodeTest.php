<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\PlaylistController;
use App\Models\Playlist;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistShareCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_playlist_creation_auto_generates_share_code(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/playlists', ['name' => 'Test Playlist']);

        $response->assertStatus(201);

        $data = $response->json('data');
        $this->assertArrayHasKey('share_code', $data);
        $this->assertNotNull($data['share_code']);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $data['share_code']);
    }

    public function test_share_codes_are_unique_across_playlists(): void
    {
        $user = User::factory()->create();

        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user)
                ->postJson('/api/playlists', ['name' => "Playlist $i"]);
            $response->assertStatus(201);
            $codes[] = $response->json('data.share_code');
        }

        $this->assertCount(5, array_unique($codes), 'Share codes must be unique');
    }

    public function test_redeem_valid_share_code_returns_playlist(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create([
            'user_id' => $user->id,
            'share_code' => 'ABCD1234',
        ]);

        $other = User::factory()->create();

        $response = $this->actingAs($other)
            ->postJson('/api/playlists/redeem', ['share_code' => 'ABCD1234']);

        $response->assertStatus(200)
            ->assertJsonPath('data.playlist.id', $playlist->id);
    }

    public function test_redeem_invalid_share_code_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/playlists/redeem', ['share_code' => 'ZZZZZZZZ']);

        $response->assertStatus(404);
    }

    public function test_share_code_present_in_playlist_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/playlists', ['name' => 'My List']);

        $response = $this->actingAs($user)->getJson('/api/playlists');
        $response->assertStatus(200);

        $first = $response->json('data.0');
        $this->assertArrayHasKey('share_code', $first);
        $this->assertNotNull($first['share_code']);
    }

    public function test_share_code_never_null_after_successful_creation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/playlists', ['name' => 'Guaranteed Code Playlist']);

        $response->assertStatus(201);
        $code = $response->json('data.share_code');
        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
        $this->assertDatabaseHas('playlists', ['share_code' => $code]);
    }

    public function test_503_returned_when_collision_exhausted_and_no_playlist_created(): void
    {
        // Override the controller with a subclass that simulates collision exhaustion
        // by returning null from generateShareCode(). This deterministically tests the
        // guard clause without relying on random number manipulation.
        $this->app->bind(PlaylistController::class, function () {
            return new class extends PlaylistController {
                protected function generateShareCode(): ?string
                {
                    return null; // all 10 retry attempts "collide"
                }
            };
        });

        $user = User::factory()->create();
        $countBefore = Playlist::where('user_id', $user->id)->count();

        $response = $this->actingAs($user)
            ->postJson('/api/playlists', ['name' => 'Should Fail']);

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Could not generate a unique share code. Please try again.');

        $countAfter = Playlist::where('user_id', $user->id)->count();
        $this->assertSame($countBefore, $countAfter, 'No playlist row should be persisted on 503');
    }
}
