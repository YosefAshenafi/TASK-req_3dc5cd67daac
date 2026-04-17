<?php

namespace App\Services;

class MediaValidator
{
    // Size limits in bytes
    private const IMAGE_DOC_MAX_BYTES = 25 * 1024 * 1024;   // 25 MB
    private const VIDEO_MAX_BYTES     = 250 * 1024 * 1024;  // 250 MB

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'application/pdf',
        'audio/mpeg',
        'video/mp4',
    ];

    // MIME types treated as video/audio (higher size limit)
    private const LARGE_MIME_TYPES = [
        'video/mp4',
        'audio/mpeg',
    ];

    /**
     * Validate the file at $tempPath.
     *
     * @param  string  $tempPath     Path to the temporary uploaded file
     * @param  string  $declaredMime The MIME type as declared by the client
     * @return array{valid: bool, reason_code: string, reason: string, sha256: string|null}
     */
    public function validate(string $tempPath, string $declaredMime): array
    {
        if (! file_exists($tempPath)) {
            return $this->fail('file_not_found', 'Temporary file not found.');
        }

        if (! in_array($declaredMime, self::ALLOWED_MIME_TYPES, true)) {
            return $this->fail('mime_not_allowed', "File type '{$declaredMime}' is not permitted. Allowed: JPEG, PNG, PDF, MP3, MP4.");
        }

        $size = filesize($tempPath);

        // Check size limit based on MIME category
        $maxBytes = in_array($declaredMime, self::LARGE_MIME_TYPES, true)
            ? self::VIDEO_MAX_BYTES
            : self::IMAGE_DOC_MAX_BYTES;

        if ($size > $maxBytes) {
            $maxMB = $maxBytes / 1024 / 1024;
            return $this->fail('file_too_large', "File exceeds maximum size of {$maxMB}MB for type {$declaredMime}.");
        }

        // Read first 12 bytes for magic byte detection
        $handle = fopen($tempPath, 'rb');
        if ($handle === false) {
            return $this->fail('file_unreadable', 'Cannot read the uploaded file.');
        }

        $header = fread($handle, 12);
        fclose($handle);

        if ($header === false || strlen($header) < 4) {
            return $this->fail('file_too_small', 'File is too small to determine type.');
        }

        // Validate magic bytes
        $magicResult = $this->checkMagicBytes($header, $declaredMime);
        if (! $magicResult['valid']) {
            return $magicResult;
        }

        // Compute SHA-256
        $sha256 = hash_file('sha256', $tempPath);

        return [
            'valid'       => true,
            'reason_code' => '',
            'reason'      => '',
            'sha256'      => $sha256,
        ];
    }

    /**
     * Check the magic bytes of the file against the declared MIME type.
     */
    private function checkMagicBytes(string $header, string $declaredMime): array
    {
        $bytes = bin2hex($header);

        switch ($declaredMime) {
            case 'image/jpeg':
                if (str_starts_with($bytes, 'ffd8ff')) {
                    return $this->ok();
                }
                return $this->fail('magic_mismatch', 'File does not appear to be a JPEG image.');

            case 'image/png':
                if (str_starts_with($bytes, '89504e47')) {
                    return $this->ok();
                }
                return $this->fail('magic_mismatch', 'File does not appear to be a PNG image.');

            case 'application/pdf':
                if (str_starts_with($bytes, '25504446')) {
                    return $this->ok();
                }
                return $this->fail('magic_mismatch', 'File does not appear to be a PDF document.');

            case 'audio/mpeg':
                // ID3 tag: 494433, or MP3 frame sync: fffb, fff3, fff2
                if (
                    str_starts_with($bytes, '494433') ||
                    str_starts_with($bytes, 'fffb') ||
                    str_starts_with($bytes, 'fff3') ||
                    str_starts_with($bytes, 'fff2')
                ) {
                    return $this->ok();
                }
                return $this->fail('magic_mismatch', 'File does not appear to be a valid MP3 audio file.');

            case 'video/mp4':
                // MP4: check for 'ftyp' box at byte offset 4 (bytes 4-7 in hex = offset 8-15)
                // header bytes 4..7 should be 66747970 ('ftyp')
                if (strlen($header) >= 8 && substr($bytes, 8, 8) === '66747970') {
                    return $this->ok();
                }
                return $this->fail('magic_mismatch', 'File does not appear to be a valid MP4 video file.');

            default:
                return $this->fail('mime_not_allowed', "Unrecognized MIME type '{$declaredMime}'.");
        }
    }

    private function ok(): array
    {
        return ['valid' => true, 'reason_code' => '', 'reason' => '', 'sha256' => null];
    }

    private function fail(string $reasonCode, string $reason): array
    {
        return ['valid' => false, 'reason_code' => $reasonCode, 'reason' => $reason, 'sha256' => null];
    }
}
