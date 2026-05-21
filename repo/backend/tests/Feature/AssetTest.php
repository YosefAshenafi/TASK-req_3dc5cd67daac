<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateThumbnailsJob;
use App\Jobs\IndexAssetJob;
use App\Models\Asset;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;
use App\Services\ScanAdapterInterface;
use App\Services\ScanHookService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Queue::fake();
    }

    public function test_asset_list_requires_auth(): void
    {
        $this->getJson('/api/assets')->assertStatus(401);
    }

    public function test_asset_list_returns_approved_assets(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/assets');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_asset_upload_requires_auth(): void
    {
        $this->postJson('/api/assets', [])->assertStatus(401);
    }

    public function test_asset_upload_validation_failure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/assets', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_asset_upload_rejects_invalid_mime(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('test.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'Test Asset',
            ]);

        $response->assertStatus(422);
    }

    public function test_asset_upload_succeeds_with_mp3(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('test.mp3', 512, 'audio/mpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'Test Audio',
                'tags' => ['test'],
                'duration' => 30,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'title', 'mime_type', 'status']]);
    }

    public function test_asset_show_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/assets/99999')
            ->assertStatus(404);
    }

    public function test_asset_delete_blocked_when_in_playlist(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        PlaylistItem::create([
            'playlist_id' => \App\Models\Playlist::factory()->create(['user_id' => $user->id])->id,
            'asset_id' => $asset->id,
            'position' => 0,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertStatus(409);
    }

    public function test_asset_delete_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);

        $this->actingAs($other)
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertStatus(403);
    }

    public function test_asset_delete_succeeds_for_owner(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_asset_search_by_query(): void
    {
        $user = User::factory()->create();

        $asset = Asset::factory()->approved()->create([
            'title' => 'Unique Announcement Clip',
            'uploaded_by' => $user->id,
        ]);
        \Illuminate\Support\Facades\DB::table('asset_search_index')->insert([
            'asset_id' => $asset->id,
            'search_text' => 'Unique Announcement Clip',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets?q=Announcement');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($asset->id, $ids);
    }

    public function test_asset_sort_by_most_played(): void
    {
        $user = User::factory()->create();

        Asset::factory()->approved()->create(['play_count' => 5, 'uploaded_by' => $user->id]);
        Asset::factory()->approved()->create(['play_count' => 100, 'uploaded_by' => $user->id]);
        Asset::factory()->approved()->create(['play_count' => 50, 'uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets?sort=most_played');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $playCounts = collect($data)->pluck('play_count')->all();
        for ($i = 0; $i < count($playCounts) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $playCounts[$i + 1],
                $playCounts[$i],
                'Assets should be ordered by play_count descending'
            );
        }
    }

    // ---------------------------------------------------------------
    // store() — scan-rejection branch
    // ---------------------------------------------------------------

    public function test_asset_upload_fails_when_scan_adapter_rejects(): void
    {
        $user = User::factory()->create();

        // Register a real adapter that always rejects on the singleton
        $scanner = $this->app->make(ScanHookService::class);
        $scanner->registerAdapter(new class implements ScanAdapterInterface {
            public function scan(string $filePath): bool
            {
                return false;
            }
        });

        $file = UploadedFile::fake()->create('clean.mp3', 512, 'audio/mpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'Should Be Rejected',
                'duration' => 10,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'File failed security scan.');
    }

    // ---------------------------------------------------------------
    // store() — DB persistence and job dispatch
    // ---------------------------------------------------------------

    public function test_asset_upload_stores_db_record_as_pending(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('track.mp3', 512, 'audio/mpeg');

        $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'Brand New Track',
                'duration' => 45,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('assets', [
            'title' => 'Brand New Track',
            'status' => 'pending',
            'uploaded_by' => $user->id,
            'play_count' => 0,
        ]);
    }

    public function test_asset_upload_dispatches_thumbnail_and_index_jobs(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('track.mp3', 512, 'audio/mpeg');

        $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'Job Dispatch Test',
                'duration' => 30,
            ])
            ->assertStatus(201);

        Queue::assertDispatched(GenerateThumbnailsJob::class);
        Queue::assertDispatched(IndexAssetJob::class);
    }

    public function test_asset_upload_stores_optional_description(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('clip.mp3', 512, 'audio/mpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'With Description',
                'description' => 'This is a test description.',
                'duration' => 20,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('assets', [
            'title' => 'With Description',
            'description' => 'This is a test description.',
        ]);
    }

    public function test_asset_upload_accepts_jpeg(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'JPEG Image',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_asset_upload_accepts_pdf(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 512, 'application/pdf');

        $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'PDF Document',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    // ---------------------------------------------------------------
    // destroy() — admin override branch
    // ---------------------------------------------------------------

    public function test_asset_delete_allowed_for_admin(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);

        $this->actingAs($admin)
            ->deleteJson("/api/assets/{$asset->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    // ---------------------------------------------------------------
    // index() — filter branches
    // ---------------------------------------------------------------

    public function test_asset_list_duration_max_filter(): void
    {
        $user = User::factory()->create();

        Asset::factory()->approved()->create(['duration' => 30, 'uploaded_by' => $user->id]);
        Asset::factory()->approved()->create(['duration' => 120, 'uploaded_by' => $user->id]);
        $short = Asset::factory()->approved()->create(['duration' => 15, 'uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets?duration_max=30');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($short->id, $ids);

        $durations = collect($response->json('data'))->pluck('duration')->filter()->all();
        foreach ($durations as $d) {
            $this->assertLessThanOrEqual(30, $d, 'All returned assets must have duration ≤ 30');
        }
    }

    public function test_asset_list_recency_days_filter(): void
    {
        $user = User::factory()->create();

        $old = Asset::factory()->approved()->create([
            'uploaded_by' => $user->id,
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ]);
        $recent = Asset::factory()->approved()->create([
            'uploaded_by' => $user->id,
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets?recency_days=7');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_asset_list_tags_filter(): void
    {
        $user = User::factory()->create();

        Asset::factory()->approved()->create([
            'tags' => ['safety'],
            'uploaded_by' => $user->id,
        ]);
        $noTag = Asset::factory()->approved()->create([
            'tags' => ['music'],
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets?' . http_build_query(['tags' => ['safety']]));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($noTag->id, $ids);
    }

    public function test_asset_list_sort_recommended_returns_200(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/assets?sort=recommended');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    // ---------------------------------------------------------------
    // index() — precise content assertions
    // ---------------------------------------------------------------

    public function test_asset_list_excludes_pending_assets_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $pending = Asset::factory()->create([
            'status' => 'pending',
            'uploaded_by' => $owner->id,
        ]);

        $response = $this->actingAs($viewer)->getJson('/api/assets');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($pending->id, $ids, 'Pending assets must not appear in the list for non-owners');
    }

    public function test_asset_list_pagination_meta_contains_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/assets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta'  => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);
    }

    // ---------------------------------------------------------------
    // store() — precise error-message assertions
    // ---------------------------------------------------------------

    public function test_asset_upload_invalid_mime_error_body_contains_file_errors(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('bad.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'Bad File',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['file']]);
    }

    public function test_asset_upload_missing_title_error_body_contains_title_key(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('track.mp3', 512, 'audio/mpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', ['file' => $file]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['title']]);
    }

    public function test_asset_upload_scan_rejection_returns_409_with_message(): void
    {
        $user = User::factory()->create();

        $scanner = $this->app->make(\App\Services\ScanHookService::class);
        $scanner->registerAdapter(new class implements \App\Services\ScanAdapterInterface {
            public function scan(string $filePath): bool { return false; }
        });

        $file = UploadedFile::fake()->create('track.mp3', 512, 'audio/mpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'Rejected Track',
                'duration' => 10,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'File failed security scan.')
            ->assertJsonStructure(['message', 'errors']);
    }

    // ---------------------------------------------------------------
    // show() — data-structure assertions
    // ---------------------------------------------------------------

    public function test_asset_show_response_contains_required_fields(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/assets/{$asset->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'mime_type', 'status', 'play_count', 'tags', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('data.id', $asset->id)
            ->assertJsonPath('data.status', 'approved');
    }

    // ---------------------------------------------------------------
    // destroy() — precise response assertions
    // ---------------------------------------------------------------

    public function test_asset_delete_returns_no_content_body(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/assets/{$asset->id}");

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
    }

    public function test_asset_delete_forbidden_response_has_message_field(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);

        $response = $this->actingAs($other)
            ->deleteJson("/api/assets/{$asset->id}");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_asset_delete_conflict_response_contains_message(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);
        $playlist = Playlist::factory()->create(['user_id' => $user->id]);
        PlaylistItem::create(['playlist_id' => $playlist->id, 'asset_id' => $asset->id, 'position' => 0]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/assets/{$asset->id}");

        $response->assertStatus(409)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertStringContainsString('playlist', strtolower($response->json('message')));
    }

    // ---------------------------------------------------------------
    // show() — access-control branches for non-approved assets
    // ---------------------------------------------------------------

    public function test_asset_show_returns_404_for_pending_asset_viewed_by_non_owner(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $asset = Asset::factory()->create([
            'uploaded_by' => $owner->id,
            'status' => 'pending',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/assets/{$asset->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_asset_show_returns_200_for_pending_asset_viewed_by_owner(): void
    {
        $owner = User::factory()->create();
        $asset = Asset::factory()->create([
            'uploaded_by' => $owner->id,
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/assets/{$asset->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $asset->id);
    }

    public function test_asset_show_returns_200_for_pending_asset_viewed_by_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();
        $asset = Asset::factory()->create([
            'uploaded_by' => $owner->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->getJson("/api/assets/{$asset->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $asset->id);
    }

    // ---------------------------------------------------------------
    // index() — sort and pagination edge combinations
    // ---------------------------------------------------------------

    public function test_asset_list_unknown_sort_param_defaults_to_newest_order(): void
    {
        $user = User::factory()->create();
        Asset::factory()->approved()->create(['uploaded_by' => $user->id, 'created_at' => now()->subHours(2)]);
        Asset::factory()->approved()->create(['uploaded_by' => $user->id, 'created_at' => now()->subHour()]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets?sort=unknown_sort_value');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        // Default newest order: most recently created is first
        $first = new \DateTime($data[0]['created_at']);
        $second = new \DateTime($data[1]['created_at']);
        $this->assertGreaterThanOrEqual($second->getTimestamp(), $first->getTimestamp());
    }

    public function test_asset_list_multiple_tags_filter_applies_and_logic(): void
    {
        $user = User::factory()->create();
        // Asset matching both tags
        Asset::factory()->approved()->create([
            'uploaded_by' => $user->id,
            'tags' => ['safety', 'morning'],
        ]);
        // Asset matching only one tag
        Asset::factory()->approved()->create([
            'uploaded_by' => $user->id,
            'tags' => ['safety'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets?tags[]=safety&tags[]=morning');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertContains('safety', $item['tags']);
            $this->assertContains('morning', $item['tags']);
        }
    }

    public function test_asset_list_pagination_defaults_to_20_per_page(): void
    {
        $user = User::factory()->create();
        // Create 25 approved assets
        Asset::factory()->approved()->count(25)->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/assets');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertSame(20, $meta['per_page']);
        $this->assertGreaterThan(1, $meta['last_page']);
    }

    // ---------------------------------------------------------------
    // store() — tags and metadata edge cases
    // ---------------------------------------------------------------

    public function test_asset_upload_with_no_tags_stores_empty_array(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('track.mp3', 512, 'audio/mpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'No Tags Track',
                'duration' => 30,
            ]);

        $response->assertStatus(201);
        $assetId = $response->json('data.id');
        $asset = Asset::find($assetId);
        $this->assertEquals([], $asset->tags);
    }

    public function test_asset_upload_succeeds_when_duration_is_omitted(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('track.mp3', 512, 'audio/mpeg');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file' => $file,
                'title' => 'No Duration Track',
            ]);

        $response->assertStatus(201);
    }

    // ---------------------------------------------------------------
    // index() — q search with no match returns empty data array
    // ---------------------------------------------------------------

    public function test_asset_list_search_with_no_match_returns_empty_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/assets?q=zzznomatchqueryzzzxxx');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // ---------------------------------------------------------------
    // index() — sort=recommended degradation fallback
    // ---------------------------------------------------------------

    public function test_asset_list_sort_recommended_falls_back_to_most_played_when_degraded(): void
    {
        $user = User::factory()->create();

        // Create approved assets with distinct, very high play counts so they
        // sort above any seeded data and their relative order is deterministic.
        $low  = Asset::factory()->approved()->create(['play_count' => 10_001]);
        $mid  = Asset::factory()->approved()->create(['play_count' => 10_002]);
        $high = Asset::factory()->approved()->create(['play_count' => 10_003]);

        // Force the degradation engine into the disabled state.
        Cache::put('rec_engine_disabled', true, 300);

        $response = $this->actingAs($user)->getJson('/api/assets?sort=recommended');

        Cache::forget('rec_engine_disabled');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $posHigh = array_search($high->id, $ids);
        $posMid  = array_search($mid->id, $ids);
        $posLow  = array_search($low->id, $ids);

        $this->assertNotFalse($posHigh);
        $this->assertNotFalse($posMid);
        $this->assertNotFalse($posLow);
        $this->assertLessThan($posMid, $posHigh, 'Highest play_count must precede mid');
        $this->assertLessThan($posLow, $posMid,  'Mid play_count must precede lowest');
    }

    public function test_asset_list_sort_recommended_uses_score_ordering_when_not_degraded(): void
    {
        $user = User::factory()->create();

        Cache::forget('rec_engine_disabled');

        $first  = Asset::factory()->approved()->create(['play_count' => 0]);
        $second = Asset::factory()->approved()->create(['play_count' => 0]);
        $third  = Asset::factory()->approved()->create(['play_count' => 0]);

        // Assign recommendation scores so the ordering does NOT match insertion order.
        DB::table('recommendation_scores')->insert([
            ['user_id' => $user->id, 'asset_id' => $first->id,  'score' => 90, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'asset_id' => $second->id, 'score' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'asset_id' => $third->id,  'score' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($user)->getJson('/api/assets?sort=recommended');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $posFirst  = array_search($first->id, $ids);
        $posSecond = array_search($second->id, $ids);
        $posThird  = array_search($third->id, $ids);

        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posSecond);
        $this->assertNotFalse($posThird);
        $this->assertLessThan($posSecond, $posFirst,  'Score 90 must precede score 50');
        $this->assertLessThan($posThird,  $posSecond, 'Score 50 must precede score 10');
    }
}
