<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\StructuredLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StructuredLoggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_successful_request_does_not_increment_error_counter(): void
    {
        $cacheKey = 'api_errors_' . date('YmdH');

        $this->getJson('/api/health')->assertStatus(200);

        $this->assertEquals(0, (int) Cache::get($cacheKey, 0));
    }

    public function test_unauthenticated_request_increments_error_counter(): void
    {
        $cacheKey = 'api_errors_' . date('YmdH');
        $before = (int) Cache::get($cacheKey, 0);

        $this->getJson('/api/admin/monitoring')->assertStatus(401);

        $after = (int) Cache::get($cacheKey, 0);
        $this->assertGreaterThan($before, $after);
    }

    public function test_not_found_request_increments_error_counter(): void
    {
        $cacheKey = 'api_errors_' . date('YmdH');
        $before = (int) Cache::get($cacheKey, 0);

        $this->getJson('/api/nonexistent-route-xyz');

        $after = (int) Cache::get($cacheKey, 0);
        $this->assertGreaterThan($before, $after);
    }

    public function test_mask_sensitive_redacts_password(): void
    {
        $result = StructuredLogger::maskSensitive(['password' => 'secret123', 'name' => 'Alice']);

        $this->assertEquals('[REDACTED]', $result['password']);
        $this->assertEquals('Alice', $result['name']);
    }

    public function test_mask_sensitive_redacts_token(): void
    {
        $result = StructuredLogger::maskSensitive(['token' => 'my-api-token', 'email' => 'a@b.com']);

        $this->assertEquals('[REDACTED]', $result['token']);
    }

    public function test_mask_sensitive_redacts_nested_keys(): void
    {
        $result = StructuredLogger::maskSensitive([
            'user' => ['password' => 'hunter2', 'username' => 'alice'],
        ]);

        $this->assertEquals('[REDACTED]', $result['user']['password']);
        $this->assertEquals('alice', $result['user']['username']);
    }

    public function test_mask_sensitive_is_case_insensitive_for_key_names(): void
    {
        $result = StructuredLogger::maskSensitive(['Authorization' => 'Bearer xyz']);

        $this->assertEquals('[REDACTED]', $result['Authorization']);
    }

    // ---------------------------------------------------------------
    // maskSensitive() — remaining sensitive keys
    // ---------------------------------------------------------------

    public function test_mask_sensitive_redacts_secret(): void
    {
        $result = StructuredLogger::maskSensitive(['secret' => 'my-webhook-secret']);

        $this->assertEquals('[REDACTED]', $result['secret']);
    }

    public function test_mask_sensitive_redacts_api_key(): void
    {
        $result = StructuredLogger::maskSensitive(['api_key' => 'sk-1234567890']);

        $this->assertEquals('[REDACTED]', $result['api_key']);
    }

    public function test_mask_sensitive_redacts_password_confirmation(): void
    {
        $result = StructuredLogger::maskSensitive([
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ]);

        $this->assertEquals('[REDACTED]', $result['password']);
        $this->assertEquals('[REDACTED]', $result['password_confirmation']);
    }

    public function test_mask_sensitive_does_not_redact_non_sensitive_key(): void
    {
        $result = StructuredLogger::maskSensitive(['username' => 'alice', 'role' => 'admin']);

        $this->assertEquals('alice', $result['username']);
        $this->assertEquals('admin', $result['role']);
    }

    // ---------------------------------------------------------------
    // handle() — 5xx response also increments counter
    // ---------------------------------------------------------------

    public function test_5xx_response_increments_error_counter(): void
    {
        $cacheKey = 'api_errors_' . date('YmdH');
        $before = (int) Cache::get($cacheKey, 0);

        $middleware = new StructuredLogger();
        $request = Request::create('/api/test', 'GET');

        $middleware->handle($request, fn ($r) => response()->json(['error' => 'boom'], 500));

        $after = (int) Cache::get($cacheKey, 0);
        $this->assertGreaterThan($before, $after);
    }

    // ---------------------------------------------------------------
    // handle() — 2xx does NOT increment counter (direct invocation)
    // ---------------------------------------------------------------

    public function test_2xx_response_does_not_increment_via_direct_call(): void
    {
        $cacheKey = 'api_errors_' . date('YmdH');
        Cache::forget($cacheKey);

        $middleware = new StructuredLogger();
        $request = Request::create('/api/test', 'GET');

        $middleware->handle($request, fn ($r) => response()->json(['ok' => true], 200));

        $this->assertEquals(0, (int) Cache::get($cacheKey, 0));
    }

    // ---------------------------------------------------------------
    // handle() — counter is persisted in cache after increment
    // ---------------------------------------------------------------

    public function test_error_counter_cache_key_is_set_and_readable_after_4xx(): void
    {
        $cacheKey = 'api_errors_' . date('YmdH');
        Cache::forget($cacheKey);

        $middleware = new StructuredLogger();
        $request = Request::create('/api/test', 'GET');

        $middleware->handle($request, fn ($r) => response()->json([], 422));

        $this->assertNotNull(Cache::get($cacheKey));
        $this->assertGreaterThanOrEqual(1, (int) Cache::get($cacheKey));
    }

    // ---------------------------------------------------------------
    // handle() — multiple errors accumulate in the same hour key
    // ---------------------------------------------------------------

    public function test_multiple_errors_accumulate_in_counter(): void
    {
        $cacheKey = 'api_errors_' . date('YmdH');
        Cache::forget($cacheKey);

        $middleware = new StructuredLogger();
        $request = Request::create('/api/test', 'GET');

        $middleware->handle($request, fn ($r) => response()->json([], 400));
        $middleware->handle($request, fn ($r) => response()->json([], 404));
        $middleware->handle($request, fn ($r) => response()->json([], 500));

        $this->assertGreaterThanOrEqual(3, (int) Cache::get($cacheKey, 0));
    }
}
