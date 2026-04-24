<?php

use App\Services\MediaProbe;

test('MediaProbe returns null for non-audio non-video mime types', function () {
    $probe = new MediaProbe();

    $duration = $probe->getDurationSeconds('/tmp/fake.jpg', 'image/jpeg');

    expect($duration)->toBeNull();
});

test('MediaProbe returns null when ffprobe output is unavailable', function () {
    $probe = new MediaProbe();

    $duration = $probe->getDurationSeconds('/tmp/definitely-missing-file.mp3', 'audio/mpeg');

    expect($duration)->toBeNull();
});
