<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * True no-mock HTTP tests for asset endpoints.
 * No Queue::fake(), Storage::fake(), or Event::fake() are used so
 * real job dispatch, real storage writes, and real DB persistence are exercised.
 */
class AssetNoMockHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ---------------------------------------------------------------
    // POST /api/assets — success path
    // ---------------------------------------------------------------

    public function test_upload_mp3_creates_db_record_and_returns_201(): void
    {
        $user = User::factory()->create();
        $file = $this->fakeAudioFile('clip.mp3');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file'     => $file,
                'title'    => 'No-Mock Clip',
                'duration' => 15,
                'tags'     => ['announcement'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'title', 'mime_type', 'status']])
            ->assertJsonPath('data.title', 'No-Mock Clip')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.mime_type', 'audio/mpeg');

        $this->assertDatabaseHas('assets', [
            'title'       => 'No-Mock Clip',
            'uploaded_by' => $user->id,
            'status'      => 'pending',
            'play_count'  => 0,
        ]);
    }

    public function test_upload_response_id_is_integer(): void
    {
        $user = User::factory()->create();
        $file = $this->fakeAudioFile('track.mp3');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file'  => $file,
                'title' => 'Integer ID Check',
            ]);

        $response->assertStatus(201);
        $this->assertIsInt($response->json('data.id'));
    }

    public function test_upload_creates_search_index_entry(): void
    {
        $user = User::factory()->create();
        $file = $this->fakeAudioFile('track.mp3');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file'  => $file,
                'title' => 'Indexed Track',
                'tags'  => ['safety'],
            ]);

        $response->assertStatus(201);
        $assetId = $response->json('data.id');
        $this->assertDatabaseHas('asset_search_index', ['asset_id' => $assetId]);
    }

    // ---------------------------------------------------------------
    // POST /api/assets — validation failure paths
    // ---------------------------------------------------------------

    public function test_upload_missing_all_fields_returns_422_with_error_keys(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/assets', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonPath('errors.file.0', fn ($v) => is_string($v))
            ->assertJsonPath('errors.title.0', fn ($v) => is_string($v));
    }

    public function test_upload_missing_title_returns_422_with_title_error(): void
    {
        $user = User::factory()->create();
        $file = $this->fakeAudioFile('track.mp3');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', ['file' => $file]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['title']]);
    }

    public function test_upload_invalid_mime_returns_422_with_file_error(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($user)
            ->postJson('/api/assets', [
                'file'  => $file,
                'title' => 'Bad File',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['file']]);
    }

    // ---------------------------------------------------------------
    // DELETE /api/assets/{asset} — owner success
    // ---------------------------------------------------------------

    public function test_owner_can_delete_own_asset(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/assets/{$asset->id}");

        $response->assertStatus(204);
        $this->assertEmpty($response->getContent());
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_owner_delete_removes_asset_from_list(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $user->id]);

        $this->actingAs($user)->deleteJson("/api/assets/{$asset->id}");

        $listResponse = $this->actingAs($user)->getJson('/api/assets');
        $listResponse->assertStatus(200);
        $ids = collect($listResponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($asset->id, $ids);
    }

    // ---------------------------------------------------------------
    // DELETE /api/assets/{asset} — forbidden path
    // ---------------------------------------------------------------

    public function test_non_owner_delete_returns_403_with_message(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);

        $response = $this->actingAs($other)
            ->deleteJson("/api/assets/{$asset->id}");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_non_owner_delete_does_not_soft_delete_asset(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $asset = Asset::factory()->approved()->create(['uploaded_by' => $owner->id]);

        $this->actingAs($other)->deleteJson("/api/assets/{$asset->id}");

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'deleted_at' => null]);
    }
}
