<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Favorite;
use App\Models\RecommendationScore;
use App\Models\User;
use App\Services\DegradationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_recommendations_return_expected_structure(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['tags' => ['safety', 'training']]);

        RecommendationScore::create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'score' => 8.5,
            'computed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/recommendations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'recommendation_reason', 'recommendation_score'],
                ],
            ]);
    }

    public function test_recommendation_reason_uses_favorite_tag_overlap(): void
    {
        $user = User::factory()->create();

        $favorited = Asset::factory()->approved()->create([
            'tags' => ['safety', 'overnight'],
        ]);
        Favorite::create(['user_id' => $user->id, 'asset_id' => $favorited->id]);

        $recommended = Asset::factory()->approved()->create([
            'tags' => ['safety', 'training'],
        ]);

        RecommendationScore::create([
            'user_id' => $user->id,
            'asset_id' => $recommended->id,
            'score' => 9.0,
            'computed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/recommendations');

        $response->assertStatus(200);
        $reason = collect($response->json('data'))
            ->firstWhere('id', $recommended->id)['recommendation_reason'] ?? '';

        $this->assertStringStartsWith('Based on your favorites:', $reason);
        $this->assertStringContainsString('Safety', $reason);
    }

    public function test_recommendation_reason_falls_back_when_no_tag_overlap(): void
    {
        $user = User::factory()->create();

        $favorited = Asset::factory()->approved()->create(['tags' => ['music', 'holiday']]);
        Favorite::create(['user_id' => $user->id, 'asset_id' => $favorited->id]);

        $recommended = Asset::factory()->approved()->create(['tags' => ['safety', 'gate']]);

        RecommendationScore::create([
            'user_id' => $user->id,
            'asset_id' => $recommended->id,
            'score' => 5.0,
            'computed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/recommendations');

        $response->assertStatus(200);
        $reason = collect($response->json('data'))
            ->firstWhere('id', $recommended->id)['recommendation_reason'] ?? '';

        $this->assertSame('Based on your listening history', $reason);
    }

    public function test_recommendation_reason_includes_up_to_3_tags(): void
    {
        $user = User::factory()->create();

        $favorited = Asset::factory()->approved()->create([
            'tags' => ['safety', 'overnight', 'gate-issues', 'training'],
        ]);
        Favorite::create(['user_id' => $user->id, 'asset_id' => $favorited->id]);

        $recommended = Asset::factory()->approved()->create([
            'tags' => ['safety', 'overnight', 'gate-issues', 'training'],
        ]);

        RecommendationScore::create([
            'user_id' => $user->id,
            'asset_id' => $recommended->id,
            'score' => 7.5,
            'computed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/recommendations');

        $reason = collect($response->json('data'))
            ->firstWhere('id', $recommended->id)['recommendation_reason'] ?? '';

        $this->assertStringStartsWith('Based on your favorites:', $reason);
        // At most 3 tags listed
        $tagPart = str_replace('Based on your favorites: ', '', $reason);
        $this->assertLessThanOrEqual(3, count(explode(', ', $tagPart)));
    }

    public function test_fallback_to_most_played_when_engine_disabled(): void
    {
        $user = User::factory()->create();

        $asset = Asset::factory()->approved()->create(['play_count' => 999]);

        // Disable the engine
        $degradation = app(DegradationService::class);
        for ($i = 0; $i < 10; $i++) {
            $degradation->recordLatency(1500.0);
        }

        $response = $this->actingAs($user)->getJson('/api/recommendations');

        $response->assertStatus(200)
            ->assertJsonPath('fallback', true);

        $reasons = collect($response->json('data'))->pluck('recommendation_reason');
        $this->assertTrue($reasons->every(fn ($r) => $r === 'Popular in your facility'));
    }

    public function test_recommendations_empty_without_scores_falls_back(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/recommendations');

        $response->assertStatus(200)
            ->assertJsonPath('fallback', true);
    }

    public function test_tag_filter_api_returns_matching_assets(): void
    {
        $user = User::factory()->create();

        Asset::factory()->approved()->create(['tags' => ['safety', 'training']]);
        Asset::factory()->approved()->create(['tags' => ['safety', 'announcement']]);
        Asset::factory()->approved()->create(['tags' => ['holiday']]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets?tags[]=safety');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertGreaterThanOrEqual(2, $ids->count());

        // All returned assets must contain the 'safety' tag
        foreach ($response->json('data') as $asset) {
            $this->assertContains('safety', $asset['tags'], "Asset {$asset['id']} missing 'safety' tag");
        }
    }

    public function test_tag_filter_excludes_non_matching_assets(): void
    {
        $user = User::factory()->create();

        Asset::factory()->approved()->create(['tags' => ['safety']]);
        Asset::factory()->approved()->create(['tags' => ['holiday']]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets?tags[]=safety');

        foreach ($response->json('data') as $asset) {
            $this->assertContains('safety', $asset['tags']);
            $this->assertNotContains('holiday', $asset['tags']);
        }
    }
}
