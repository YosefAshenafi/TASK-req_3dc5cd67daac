<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Threshold
    |--------------------------------------------------------------------------
    |
    | The number of recommendation failures required to trip the circuit
    | breaker and disable the 'recommended_enabled' feature flag.
    |
    */

    'circuit_breaker_threshold'        => env('CIRCUIT_BREAKER_THRESHOLD', 10),
    'latency_p95_threshold_ms'         => (int) env('LATENCY_P95_THRESHOLD_MS', 800),
    'latency_window_minutes'           => (int) env('LATENCY_WINDOW_MINUTES', 5),
    'recommendation_hit_rate_min'      => (float) env('RECOMMENDATION_HIT_RATE_MIN', 0.10),
    'circuit_breaker_recovery_minutes' => (int) env('CIRCUIT_BREAKER_RECOVERY_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Media Storage
    |--------------------------------------------------------------------------
    |
    | Configuration for media file handling.
    |
    */

    'media' => [
        'max_image_size_bytes' => env('MEDIA_MAX_IMAGE_SIZE_BYTES', 25 * 1024 * 1024),
        'max_video_size_bytes' => env('MEDIA_MAX_VIDEO_SIZE_BYTES', 250 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Gateway
    |--------------------------------------------------------------------------
    |
    | Shared-secret token the offline gateway uses to authenticate ingest
    | requests.  Set GATEWAY_TOKEN in the environment / Docker secrets and
    | mount it into the gateway container.  Rotate by changing the value and
    | restarting the gateway service.
    |
    */

    'gateway' => [
        'token' => env('GATEWAY_TOKEN', null),
    ],

];
