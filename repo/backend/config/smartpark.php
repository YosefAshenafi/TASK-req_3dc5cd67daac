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

    'circuit_breaker_threshold' => env('CIRCUIT_BREAKER_THRESHOLD', 10),

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

];
