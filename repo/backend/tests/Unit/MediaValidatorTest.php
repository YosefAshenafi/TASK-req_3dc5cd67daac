<?php

use App\Services\MediaValidator;
use Illuminate\Http\UploadedFile;

test('MediaValidator rejects declared MP4 with PNG magic bytes', function () {
    $validator = new MediaValidator();

    // Create a fake PNG file (magic bytes: 89 50 4E 47) but declare it as MP4.
    // Server-side sniffing must refuse this regardless of the exact failure reason:
    // finfo may classify it as image/png (→ mime_mismatch), or as something outside
    // the allowlist for a tiny stub file (→ mime_not_allowed). Both are correct refusals.
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, "\x89PNG\r\n\x1A\n" . str_repeat('a', 100));

    $result = $validator->validate($tempFile, 'video/mp4');

    unlink($tempFile);

    expect($result['valid'])->toBeFalse();
    expect($result['reason_code'])->toBeIn(['magic_mismatch', 'mime_mismatch', 'mime_not_allowed']);
});

test('MediaValidator rejects file whose sniffed MIME is disallowed', function () {
    $validator = new MediaValidator();

    // A plain-text file is not in the allowlist; client declares it as PNG.
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, str_repeat("hello world\n", 200));

    $result = $validator->validate($tempFile, 'image/png');

    unlink($tempFile);

    expect($result['valid'])->toBeFalse();
    expect($result['reason_code'])->toBeIn(['mime_not_allowed', 'mime_mismatch']);
});

test('MediaValidator returns sniffed MIME in result', function () {
    $validator = new MediaValidator();

    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, "\xFF\xD8\xFF\xE0" . str_repeat('a', 200));

    $result = $validator->validate($tempFile, 'image/jpeg');
    unlink($tempFile);

    expect($result['valid'])->toBeTrue();
    expect($result)->toHaveKey('sniffed_mime');
    expect($result['sniffed_mime'])->toEqual('image/jpeg');
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

    $tempFile = write_oversized_jpeg_temp_path();

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
