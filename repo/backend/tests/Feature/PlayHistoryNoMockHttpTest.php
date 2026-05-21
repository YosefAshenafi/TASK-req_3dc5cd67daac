<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\PlayHistory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * True no-mock HTTP tests for play-history endpoints.
 * No Queue::fake() is used — ComputeUserRecommendationsJob executes via the
 * configured sync queue driver (QUEUE_CONNECTION=sync in phpunit.xml).
 */
class PlayHistoryNoMockHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ---------------------------------------------------------------
    // GET /api/play-history — auth required
    // ---------------------------------------------------------------

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/play-history')->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // GET /api/play-history — owner-only listing
    // ---------------------------------------------------------------

    public function test_list_returns_only_authenticated_users_entries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);

        PlayHistory::create(['user_id' => $owner->id, 'asset_id' => $asset->id, 'played_at' => now()]);
        PlayHistory::create(['user_id' => $other->id, 'asset_id' => $asset->id, 'played_at' => now()]);

        $response = $this->actingAs($owner)->getJson('/api/play-history');

        $response->assertStatus(200);
        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $othersEntry = PlayHistory::where('user_id', $other->id)->first();
        $this->assertNotContains($othersEntry->id, $returnedIds);
    }

    public function test_list_response_contains_played_at_and_asset_fields(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->approved()->create([
            'uploaded_by' => $user->id,
            'title'       => 'History Test Track',
        ]);
        PlayHistory::create(['user_id' => $user->id, 'asset_id' => $asset->id, 'played_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/play-history');

        $response->assertStatus(200);
        $item = $response->json('data.0');
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('played_at', $item);
        $this->assertArrayHasKey('asset', $item);
        $this->assertSame($asset->id, $item['asset']['id']);
        $this->assertSame('History Test Track', $item['asset']['title']);
    }

    public function test_list_returns_empty_array_when_user_has_no_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/play-history')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    // ---------------------------------------------------------------
    // POST /api/play-history — success path
    // ---------------------------------------------------------------

    public function test_store_creates_db_entry_and_returns_201(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'played_at']]);

        $this->assertDatabaseHas('play_history', [
            'user_id'  => $user->id,
            'asset_id' => $asset->id,
        ]);
    }

    public function test_store_increments_asset_play_count(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->approved()->create([
            'uploaded_by' => $user->id,
            'play_count'  => 3,
        ]);

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id]);

        $this->assertSame(4, $asset->fresh()->play_count);
    }

    public function test_store_response_played_at_is_iso8601(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id]);

        $response->assertStatus(201);
        $playedAt = $response->json('data.played_at');
        $this->assertStringContainsString('T', $playedAt);
        $this->assertNotFalse(\DateTime::createFromFormat(\DateTime::ATOM, $playedAt));
    }

    public function test_store_does_not_leak_user_id_in_response(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id]);

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('user_id', $response->json('data'));
    }

    // ---------------------------------------------------------------
    // POST /api/play-history — invalid asset_id (type error)
    // ---------------------------------------------------------------

    public function test_store_rejects_string_asset_id_with_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => 'not-a-number'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['asset_id']]);
    }

    public function test_store_rejects_missing_asset_id_with_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/play-history', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['asset_id']]);
    }

    // ---------------------------------------------------------------
    // POST /api/play-history — non-existent asset_id
    // ---------------------------------------------------------------

    public function test_store_rejects_non_existent_asset_id_with_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => 999999])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['asset_id']]);
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/play-history', ['asset_id' => 1])->assertStatus(401);
    }
}
