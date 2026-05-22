<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_monitoring_endpoint_requires_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/admin/monitoring')
            ->assertStatus(403);
    }

    public function test_monitoring_returns_full_structure(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/monitoring');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'health' => ['database', 'queue'],
                    'queue' => ['pending_jobs', 'failed_jobs'],
                    'recommendations' => [
                        'engine_status',
                        'window_seconds',
                        'p95_latency_ms',
                        'hit_rate',
                        'p95_threshold_ms',
                        'hit_rate_threshold',
                    ],
                    'api_errors' => ['last_hour'],
                    'device_ingestion' => ['received_count', 'late_count', 'buffered_count', 'duplicate_count'],
                    'timestamp',
                ],
            ]);
    }

    public function test_monitoring_exposes_correct_window_seconds(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->getJson('/api/admin/monitoring');
        $response->assertStatus(200)
            ->assertJsonPath('data.recommendations.window_seconds', 300)
            ->assertJsonPath('data.recommendations.p95_threshold_ms', 800)
            ->assertJsonPath('data.recommendations.hit_rate_threshold', 0.10);
    }

    public function test_api_errors_reflects_cache_counter(): void
    {
        $admin = User::factory()->admin()->create();
        $cacheKey = 'api_errors_' . date('YmdH');
        Cache::put($cacheKey, 42, 3600);

        $response = $this->actingAs($admin)->getJson('/api/admin/monitoring');
        $response->assertStatus(200);

        $errors = $response->json('data.api_errors.last_hour');
        $this->assertGreaterThanOrEqual(42, $errors);
    }

    public function test_device_ingestion_counts_are_non_negative(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/monitoring');
        $response->assertStatus(200);

        $ingestion = $response->json('data.device_ingestion');
        foreach (['received_count', 'late_count', 'buffered_count', 'duplicate_count'] as $key) {
            $this->assertGreaterThanOrEqual(0, $ingestion[$key], "$key should be >= 0");
        }
    }

    // ---------------------------------------------------------------
    // Response value assertions
    // ---------------------------------------------------------------

    public function test_monitoring_health_database_field_is_string(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertIsString(
            $this->actingAs($admin)->getJson('/api/admin/monitoring')->json('data.health.database')
        );
    }

    public function test_monitoring_health_queue_field_is_string(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertIsString(
            $this->actingAs($admin)->getJson('/api/admin/monitoring')->json('data.health.queue')
        );
    }

    public function test_monitoring_engine_status_is_active_or_disabled(): void
    {
        $admin    = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->getJson('/api/admin/monitoring');

        $status = $response->json('data.recommendations.engine_status');
        $this->assertContains($status, ['active', 'disabled'],
            "engine_status must be 'active' or 'disabled'");
    }

    public function test_monitoring_timestamp_is_non_empty(): void
    {
        $admin    = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->getJson('/api/admin/monitoring');

        $this->assertNotEmpty($response->json('data.timestamp'));
    }

    public function test_monitoring_queue_pending_and_failed_jobs_are_integers(): void
    {
        $admin    = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->getJson('/api/admin/monitoring');

        $this->assertIsInt($response->json('data.queue.pending_jobs'));
        $this->assertIsInt($response->json('data.queue.failed_jobs'));
    }
}
