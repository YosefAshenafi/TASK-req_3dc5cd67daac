<?php

use App\Models\FeatureFlag;
use App\Services\Monitoring\MetricsRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

test('MonitoringSample disables flag when p95 latency breaches threshold', function () {
    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => true, 'reason' => null, 'updated_at' => now()]
    );

    $this->mock(MetricsRecorder::class, function ($mock) {
        $mock->shouldReceive('readLatencyP95')->once()->andReturn(2000);
        $mock->shouldReceive('readRecommendationCounts')->andReturn(['requests' => 0, 'hits' => 0]);
    });

    $code = Artisan::call('app:monitoring-sample');

    expect($code)->toBe(0);

    $flag = FeatureFlag::find('recommended_enabled');
    expect($flag->enabled)->toBeFalse();
});

test('MonitoringSample recovers flag after healthy window when metrics ok', function () {
    Cache::put('circuit_breaker:degraded_since', now()->subMinutes(20)->timestamp, 3600);
    Cache::put('circuit_breaker:recommendation_failures', 0, 3600);

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => false, 'reason' => 'bad', 'updated_at' => now()]
    );

    $this->mock(MetricsRecorder::class, function ($mock) {
        $mock->shouldReceive('readLatencyP95')->andReturn(10);
        $mock->shouldReceive('readRecommendationCounts')->andReturn(['requests' => 0, 'hits' => 0]);
        $mock->shouldReceive('resetRecommendationCounters')->once();
    });

    config(['smartpark.circuit_breaker_recovery_minutes' => 15]);

    $code = Artisan::call('app:monitoring-sample');

    expect($code)->toBe(0);

    $flag = FeatureFlag::find('recommended_enabled');
    expect($flag->enabled)->toBeTrue();
});

test('MonitoringSample returns success when recommended flag row missing', function () {
    FeatureFlag::where('key', 'recommended_enabled')->delete();

    $this->mock(MetricsRecorder::class, function ($mock) {
        $mock->shouldReceive('readLatencyP95')->andReturn(0);
        $mock->shouldReceive('readRecommendationCounts')->andReturn(['requests' => 0, 'hits' => 0]);
    });

    expect(Artisan::call('app:monitoring-sample'))->toBe(0);
});

test('MonitoringSample disables flag when recommendation hit rate is below minimum', function () {
    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => true, 'reason' => null, 'updated_at' => now()]
    );

    $this->mock(MetricsRecorder::class, function ($mock) {
        $mock->shouldReceive('readLatencyP95')->andReturn(0);
        $mock->shouldReceive('readRecommendationCounts')->andReturn(['requests' => 40, 'hits' => 1]);
    });

    expect(Artisan::call('app:monitoring-sample'))->toBe(0);

    $flag = FeatureFlag::find('recommended_enabled');
    expect($flag->enabled)->toBeFalse();
    expect($flag->reason)->toContain('hit rate');
});

test('MonitoringSample disables flag when legacy failure counter exceeds threshold', function () {
    Cache::put('circuit_breaker:recommendation_failures', 15, 3600);

    FeatureFlag::updateOrCreate(
        ['key' => 'recommended_enabled'],
        ['enabled' => true, 'reason' => null, 'updated_at' => now()]
    );

    $this->mock(MetricsRecorder::class, function ($mock) {
        $mock->shouldReceive('readLatencyP95')->andReturn(0);
        $mock->shouldReceive('readRecommendationCounts')->andReturn(['requests' => 0, 'hits' => 0]);
    });

    expect(Artisan::call('app:monitoring-sample'))->toBe(0);

    $flag = FeatureFlag::find('recommended_enabled');
    expect($flag->enabled)->toBeFalse();
    expect($flag->reason)->toContain('Circuit breaker');
});
