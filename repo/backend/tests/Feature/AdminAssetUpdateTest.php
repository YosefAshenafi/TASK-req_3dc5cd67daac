<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers PATCH /api/admin/assets/{asset} — the admin-only asset edit endpoint.
 * These are true no-mock HTTP tests: each boots the real app and sends a real
 * request through the real router, middleware, controller, and database.
 */
class AdminAssetUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_requires_auth(): void
    {
        $asset = Asset::factory()->create();

        $this->patchJson("/api/admin/assets/{$asset->id}", ['title' => 'New Title'])
            ->assertStatus(401);
    }

    public function test_update_forbidden_for_regular_user(): void
    {
        $owner = User::factory()->create();
        $asset = Asset::factory()->create(['uploaded_by' => $owner->id]);

        // Even the uploader (a non-admin) cannot edit: editing is admin-only.
        $this->actingAs($owner)
            ->patchJson("/api/admin/assets/{$asset->id}", ['title' => 'Hijacked Title'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('assets', ['id' => $asset->id, 'title' => 'Hijacked Title']);
    }

    public function test_admin_can_update_title(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $asset = Asset::factory()->create([
            'uploaded_by' => $owner->id,
            'title' => 'Original Title',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/assets/{$asset->id}", ['title' => 'Updated Event']);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Event');

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'title' => 'Updated Event',
        ]);
    }

    public function test_admin_can_update_description_and_tags(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create([
            'description' => 'old description',
            'tags' => ['old'],
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/assets/{$asset->id}", [
                'description' => 'a fresh description',
                'tags' => ['safety', 'training'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.description', 'a fresh description')
            ->assertJsonPath('data.tags', ['safety', 'training']);

        $this->assertSame(['safety', 'training'], $asset->fresh()->tags);
    }

    public function test_partial_update_leaves_other_fields_untouched(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create([
            'title' => 'Keep Me',
            'description' => 'keep this description',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/assets/{$asset->id}", ['title' => 'Renamed'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.description', 'keep this description');
    }

    public function test_update_rejects_blank_title(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create(['title' => 'Original Title']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/assets/{$asset->id}", ['title' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'title' => 'Original Title']);
    }

    public function test_update_rejects_overlong_tag(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/assets/{$asset->id}", [
                'tags' => [str_repeat('x', 51)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tags.0');
    }
}
