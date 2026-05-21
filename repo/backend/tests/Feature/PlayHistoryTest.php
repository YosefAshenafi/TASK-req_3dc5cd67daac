<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ComputeUserRecommendationsJob;
use App\Models\Asset;
use App\Models\PlayHistory;
use App\Models\RecommendationScore;
use App\Models\User;
use App\Services\DegradationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlayHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
    }

    public function test_play_history_list_requires_auth(): void
    {
        $this->getJson('/api/play-history')->assertStatus(401);
    }

    public function test_play_history_list_returns_only_owner_entries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);

        PlayHistory::create(['user_id' => $owner->id, 'asset_id' => $asset->id, 'played_at' => now()]);
        PlayHistory::create(['user_id' => $other->id, 'asset_id' => $asset->id, 'played_at' => now()]);

        $response = $this->actingAs($owner)->getJson('/api/play-history');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $otherEntry = PlayHistory::where('user_id', $other->id)->first();
        $returnedIds = collect($data)->pluck('id')->all();
        $this->assertNotContains($otherEntry->id, $returnedIds);
    }

    public function test_play_history_list_returns_most_recent_first(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        PlayHistory::create(['user_id' => $user->id, 'asset_id' => $asset->id, 'played_at' => now()->subMinutes(10)]);
        PlayHistory::create(['user_id' => $user->id, 'asset_id' => $asset->id, 'played_at' => now()->subMinutes(1)]);

        $response = $this->actingAs($user)->getJson('/api/play-history');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(1, count($data));

        $first = new \DateTime($data[0]['played_at']);
        $second = new \DateTime($data[1]['played_at']);
        $this->assertGreaterThan($second->getTimestamp(), $first->getTimestamp());
    }

    public function test_post_play_history_requires_auth(): void
    {
        $this->postJson('/api/play-history', ['asset_id' => 1])->assertStatus(401);
    }

    public function test_post_play_history_validation_requires_asset_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/play-history', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['asset_id']]);
    }

    public function test_post_play_history_validates_asset_exists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => 999999])
            ->assertStatus(422);
    }

    public function test_post_play_history_success_records_entry(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);
        $originalPlayCount = $asset->play_count;

        $response = $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'played_at']]);

        $this->assertDatabaseHas('play_history', [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
        ]);

        $this->assertEquals($originalPlayCount + 1, $asset->fresh()->play_count);
    }

    // ---------------------------------------------------------------
    // store() — job dispatch assertion
    // ---------------------------------------------------------------

    public function test_post_play_history_dispatches_recommendations_job(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id])
            ->assertStatus(201);

        Queue::assertDispatched(ComputeUserRecommendationsJob::class);
    }

    // ---------------------------------------------------------------
    // store() — isRecommended=true branch: calls DegradationService::recordHit()
    // ---------------------------------------------------------------

    public function test_post_play_history_records_hit_when_asset_is_recommended(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        RecommendationScore::create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'score' => 0.9,
        ]);

        /** @var DegradationService $degradation */
        $degradation = $this->app->make(DegradationService::class);
        Cache::flush();
        // Provide baseline served count so hit rate denominator > 0
        $degradation->recordServed(5);
        $hitRateBefore = $degradation->getHitRate();

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id])
            ->assertStatus(201);

        $hitRateAfter = $degradation->getHitRate();
        $this->assertGreaterThan($hitRateBefore, $hitRateAfter, 'recordHit() should have been called for a recommended asset');
    }

    // ---------------------------------------------------------------
    // store() — isRecommended=false branch: does NOT call recordHit()
    // ---------------------------------------------------------------

    public function test_post_play_history_does_not_record_hit_for_non_recommended_asset(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);
        // No RecommendationScore row → isRecommended = false

        /** @var DegradationService $degradation */
        $degradation = $this->app->make(DegradationService::class);
        Cache::flush();
        $degradation->recordServed(5);
        $hitRateBefore = $degradation->getHitRate();

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id])
            ->assertStatus(201);

        $hitRateAfter = $degradation->getHitRate();
        $this->assertEquals($hitRateBefore, $hitRateAfter, 'recordHit() must not fire for non-recommended assets');
    }

    // ---------------------------------------------------------------
    // index() — response includes embedded asset data
    // ---------------------------------------------------------------

    public function test_play_history_list_response_includes_asset_fields(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create([
            'uploaded_by' => $user->id,
            'title' => 'History Asset Title',
        ]);
        PlayHistory::create(['user_id' => $user->id, 'asset_id' => $asset->id, 'played_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/play-history');

        $response->assertStatus(200);
        $item = $response->json('data.0');
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('played_at', $item);
        $this->assertArrayHasKey('asset', $item);
        $this->assertEquals($asset->id, $item['asset']['id']);
        $this->assertEquals('History Asset Title', $item['asset']['title']);
    }

    // ---------------------------------------------------------------
    // store() — precise response-body and timestamp-format assertions
    // ---------------------------------------------------------------

    public function test_post_play_history_response_played_at_is_iso8601(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id]);

        $response->assertStatus(201);
        $playedAt = $response->json('data.played_at');
        $this->assertNotNull($playedAt);
        // ISO 8601 contains a T separator between date and time
        $this->assertStringContainsString('T', $playedAt);
        $this->assertNotFalse(\DateTime::createFromFormat(\DateTime::ATOM, $playedAt), 'played_at must be ISO 8601');
    }

    public function test_post_play_history_response_contains_id_and_played_at_only(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'played_at']]);

        // Response should NOT leak user_id or asset_id at the top-level data key
        $data = $response->json('data');
        $this->assertArrayNotHasKey('user_id', $data);
    }

    // ---------------------------------------------------------------
    // index() — empty-list response and exact data shape
    // ---------------------------------------------------------------

    public function test_play_history_list_returns_empty_array_when_no_history(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/play-history');

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_play_history_list_played_at_field_is_iso8601(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);
        PlayHistory::create(['user_id' => $user->id, 'asset_id' => $asset->id, 'played_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/play-history');

        $response->assertStatus(200);
        $playedAt = $response->json('data.0.played_at');
        $this->assertStringContainsString('T', $playedAt);
    }

    // ---------------------------------------------------------------
    // isRecommended branch — direct DegradationService observation
    // ---------------------------------------------------------------

    public function test_post_play_history_hit_rate_unchanged_when_asset_not_in_recommendation_scores(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);
        // No RecommendationScore row — isRecommended will be false

        /** @var \App\Services\DegradationService $degradation */
        $degradation = $this->app->make(\App\Services\DegradationService::class);
        Cache::flush();
        $degradation->recordServed(10);
        $hitsBefore = $this->extractHitCount($degradation);

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id])
            ->assertStatus(201);

        $hitsAfter = $this->extractHitCount($degradation);
        $this->assertSame($hitsBefore, $hitsAfter, 'Hit count must not change for non-recommended assets');
    }

    public function test_post_play_history_increments_hit_count_for_recommended_asset(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        RecommendationScore::create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'score' => 0.85,
        ]);

        /** @var \App\Services\DegradationService $degradation */
        $degradation = $this->app->make(\App\Services\DegradationService::class);
        Cache::flush();
        $degradation->recordServed(10);
        $hitsBefore = $this->extractHitCount($degradation);

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id])
            ->assertStatus(201);

        $hitsAfter = $this->extractHitCount($degradation);
        $this->assertGreaterThan($hitsBefore, $hitsAfter, 'Hit count must increase for a recommended asset');
    }

    // ---------------------------------------------------------------
    // index() — limit and response shape
    // ---------------------------------------------------------------

    public function test_play_history_list_limited_to_50_most_recent_entries(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        // Insert 55 history entries with distinct played_at timestamps
        for ($i = 1; $i <= 55; $i++) {
            PlayHistory::create([
                'user_id' => $user->id,
                'asset_id' => $asset->id,
                'played_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/play-history');

        $response->assertStatus(200);
        $this->assertCount(50, $response->json('data'));
    }

    public function test_play_history_list_response_data_is_array(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/play-history');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }

    // ---------------------------------------------------------------
    // store() — play_count increment is exactly +1
    // ---------------------------------------------------------------

    public function test_post_play_history_increments_play_count_by_exactly_one(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create([
            'uploaded_by' => $user->id,
            'play_count' => 7,
        ]);

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id])
            ->assertStatus(201);

        $this->assertEquals(8, $asset->fresh()->play_count);
    }

    // ---------------------------------------------------------------
    // store() — validation edge: asset_id missing vs invalid type
    // ---------------------------------------------------------------

    public function test_post_play_history_validation_rejects_string_asset_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => 'not-an-integer'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['asset_id']]);
    }

    public function test_post_play_history_validation_rejects_zero_asset_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => 0])
            ->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // store() — response body shape invariants
    // ---------------------------------------------------------------

    public function test_post_play_history_response_data_key_exists(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'played_at']]);
    }

    public function test_post_play_history_response_id_matches_database_entry(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => $asset->id]);

        $response->assertStatus(201);
        $returnedId = $response->json('data.id');
        $this->assertDatabaseHas('play_history', ['id' => $returnedId, 'user_id' => $user->id]);
    }

    // ---------------------------------------------------------------
    // index() — asset_id validation: non-existent id returns 422
    // ---------------------------------------------------------------

    public function test_post_play_history_validation_rejects_non_existent_asset_with_422_body(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/play-history', ['asset_id' => 999999]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['asset_id']]);
    }

    // ---------------------------------------------------------------
    // Helper: read raw hit count from the degradation cache
    // ---------------------------------------------------------------

    private function extractHitCount(\App\Services\DegradationService $degradation): int
    {
        $log = \Illuminate\Support\Facades\Cache::get('rec_hit_window', []);
        return (int) array_sum(array_column($log, 'hits'));
    }
}
