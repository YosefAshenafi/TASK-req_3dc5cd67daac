<?php

use App\Services\Monitoring\MetricsRecorder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['cache.default' => 'redis']);
    app('cache')->forgetDriver();

    try {
        Cache::getRedis()->ping();
    } catch (\Throwable) {
        $this->markTestSkipped('Redis not available');
    }

    foreach ([
        MetricsRecorder::LATENCY_KEY,
        MetricsRecorder::REQUEST_SAMPLES_KEY,
        MetricsRecorder::REC_REQUESTS_KEY,
        MetricsRecorder::REC_HITS_KEY,
    ] as $key) {
        Cache::getRedis()->del($key);
    }
});

afterEach(function () {
    config(['cache.default' => 'array']);
    app('cache')->forgetDriver();
});

test('readLatencyP95 returns zero when fewer than five samples exist', function () {
    $metrics = app(MetricsRecorder::class);

    for ($i = 0; $i < 3; $i++) {
        $metrics->recordLatency(50);
    }

    expect($metrics->readLatencyP95(5))->toBe(0.0);
});

test('readLatencyP95 returns a positive value when enough samples exist', function () {
    $metrics = app(MetricsRecorder::class);

    for ($i = 0; $i < 10; $i++) {
        $metrics->recordLatency(100 + $i * 10);
    }

    $p95 = $metrics->readLatencyP95(5);
    expect($p95)->toBeGreaterThan(0);
});

test('recordRequest and readErrorRate reflect five hundred errors', function () {
    $metrics = app(MetricsRecorder::class);

    for ($i = 0; $i < 8; $i++) {
        $metrics->recordRequest(200);
    }
    for ($i = 0; $i < 2; $i++) {
        $metrics->recordRequest(503);
    }

    expect($metrics->readErrorRate(5))->toEqual(0.2);
});

test('readErrorRate returns zero when there are no samples', function () {
    expect(app(MetricsRecorder::class)->readErrorRate(5))->toBe(0.0);
});

test('recommendation counters increment read counts and hit rate', function () {
    $metrics = app(MetricsRecorder::class);

    for ($i = 0; $i < 4; $i++) {
        $metrics->incrementRecommendationRequests();
    }
    for ($i = 0; $i < 2; $i++) {
        $metrics->incrementRecommendationHits();
    }

    $counts = $metrics->readRecommendationCounts();
    expect($counts['requests'])->toBe(4);
    expect($counts['hits'])->toBe(2);
    expect($metrics->readRecommendationHitRate())->toBe(0.5);

    $metrics->resetRecommendationCounters();

    $after = $metrics->readRecommendationCounts();
    expect($after['requests'])->toBe(0);
    expect($after['hits'])->toBe(0);
});
