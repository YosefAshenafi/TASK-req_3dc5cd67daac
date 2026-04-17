<?php

use App\Services\MediaValidator;
use Illuminate\Http\UploadedFile;

test('MediaValidator rejects declared MP4 with PNG magic bytes', function () {
    $validator = new MediaValidator();

    // Create a fake PNG file (magic bytes: 89 50 4E 47)
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, "\x89PNG\r\n\x1A\n" . str_repeat('a', 100));

    $result = $validator->validate($tempFile, 'video/mp4');

    unlink($tempFile);

    expect($result['valid'])->toBeFalse();
    expect($result['reason_code'])->toEqual('magic_mismatch');
});

test('MediaValidator accepts valid JPEG', function () {
    $validator = new MediaValidator();

    // JPEG magic bytes: FF D8 FF
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, "\xFF\xD8\xFF\xE0" . str_repeat('a', 100));

    $result = $validator->validate($tempFile, 'image/jpeg');

    unlink($tempFile);

    expect($result['valid'])->toBeTrue();
});

test('MediaValidator rejects files over size cap', function () {
    $validator = new MediaValidator();

    // Create a 26MB fake JPEG (over 25MB image cap)
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    $bytes    = "\xFF\xD8\xFF\xE0" . str_repeat('x', 26 * 1024 * 1024);
    file_put_contents($tempFile, $bytes);

    $result = $validator->validate($tempFile, 'image/jpeg');

    unlink($tempFile);

    expect($result['valid'])->toBeFalse();
    expect($result['reason_code'])->toEqual('file_too_large');
});

test('MediaValidator computes sha256 fingerprint for valid file', function () {
    $validator = new MediaValidator();

    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, "\xFF\xD8\xFF\xE0" . str_repeat('a', 500));

    $result = $validator->validate($tempFile, 'image/jpeg');
    unlink($tempFile);

    expect($result)->toHaveKey('sha256');
    expect(strlen($result['sha256']))->toEqual(64);
});
