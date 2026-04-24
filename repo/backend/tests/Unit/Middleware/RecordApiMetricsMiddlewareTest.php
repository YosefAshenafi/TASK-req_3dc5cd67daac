<?php

use App\Http\Middleware\RecordApiMetrics;
use App\Services\Monitoring\MetricsRecorder;
use Illuminate\Http\Request;

test('RecordApiMetrics skips monitoring and health paths', function () {
    $calls = ['latency' => 0, 'request' => 0];

    $metrics = new class($calls) extends MetricsRecorder {
        public function __construct(private array &$calls) {}
        public function recordLatency(int $ms): void { $this->calls['latency']++; }
        public function recordRequest(int $statusCode): void { $this->calls['request']++; }
    };

    $middleware = new RecordApiMetrics($metrics);

    $monitoringReq = Request::create('/api/monitoring/status', 'GET');
    $healthReq = Request::create('/api/health', 'GET');

    $monitoringRes = $middleware->handle($monitoringReq, fn () => response('ok', 200));
    $healthRes = $middleware->handle($healthReq, fn () => response('ok', 200));

    expect($monitoringRes->getStatusCode())->toBe(200);
    expect($healthRes->getStatusCode())->toBe(200);
    expect($calls['latency'])->toBe(0);
    expect($calls['request'])->toBe(0);
});

test('RecordApiMetrics records latency and status for regular API paths', function () {
    $captured = ['latency' => [], 'status' => []];

    $metrics = new class($captured) extends MetricsRecorder {
        public function __construct(private array &$captured) {}
        public function recordLatency(int $ms): void { $this->captured['latency'][] = $ms; }
        public function recordRequest(int $statusCode): void { $this->captured['status'][] = $statusCode; }
    };

    $middleware = new RecordApiMetrics($metrics);
    $request = Request::create('/api/search', 'GET');

    $response = $middleware->handle($request, fn () => response('ok', 201));

    expect($response->getStatusCode())->toBe(201);
    expect($captured['status'])->toBe([201]);
    expect($captured['latency'])->toHaveCount(1);
    expect($captured['latency'][0])->toBeGreaterThanOrEqual(0);
});

test('RecordApiMetrics never breaks request when metrics recorder throws', function () {
    $metrics = new class extends MetricsRecorder {
        public function recordLatency(int $ms): void { throw new RuntimeException('boom'); }
        public function recordRequest(int $statusCode): void { throw new RuntimeException('boom'); }
    };

    $middleware = new RecordApiMetrics($metrics);
    $request = Request::create('/api/search', 'GET');

    $response = $middleware->handle($request, fn () => response('ok', 204));

    expect($response->getStatusCode())->toBe(204);
});
