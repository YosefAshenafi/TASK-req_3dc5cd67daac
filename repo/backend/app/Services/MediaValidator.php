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

    // finfo can return several aliases for MP3 audio — normalize them.
    private const MIME_ALIASES = [
        'audio/mp3'    => 'audio/mpeg',
        'audio/mpeg3'  => 'audio/mpeg',
        'audio/x-mpeg' => 'audio/mpeg',
        'audio/x-mp3'  => 'audio/mpeg',
    ];

    // finfo returns this for files it can't classify — we fall back to magic-byte checks.
    private const AMBIGUOUS_MIME_TYPES = [
        'application/octet-stream',
        'application/x-empty',
        'inode/x-empty',
    ];

    /**
     * Validate the file at $tempPath.
     *
     * Three-layer check:
     *   1. Client must declare a supported MIME.
     *   2. Server sniffs the file via finfo (authoritative when a concrete type is returned).
     *      A concrete sniffed MIME must (a) be in the allowlist and (b) match the declared MIME.
     *      If finfo is ambiguous (e.g. octet-stream), we fall back to the magic-byte check
     *      against the declared MIME.
     *   3. Magic-byte signature must match the authoritative MIME.
     *
     * Returns the server-authoritative MIME in `sniffed_mime` so the caller can persist it
     * instead of whatever the client declared.
     *
     * @param  string  $tempPath
     * @param  string  $declaredMime
     * @return array{valid: bool, reason_code: string, reason: string, sha256: string|null, sniffed_mime: string|null}
     */
    public function validate(string $tempPath, string $declaredMime): array
    {
        if (! file_exists($tempPath)) {
            return $this->fail('file_not_found', 'Temporary file not found.');
        }

        if (! in_array($declaredMime, self::ALLOWED_MIME_TYPES, true)) {
            return $this->fail('mime_not_allowed', "File type '{$declaredMime}' is not permitted. Allowed: JPEG, PNG, PDF, MP3, MP4.");
        }

        // Sniff the real type. This is the server-side authoritative check.
        $sniffedMime = $this->sniffMime($tempPath);

        // Decide which MIME we'll treat as authoritative for the rest of the checks.
        $authoritativeMime = $declaredMime;

        if ($sniffedMime !== null && ! in_array($sniffedMime, self::AMBIGUOUS_MIME_TYPES, true)) {
            // finfo produced a concrete answer — enforce it.
            if (! in_array($sniffedMime, self::ALLOWED_MIME_TYPES, true)) {
                return $this->fail(
                    'mime_not_allowed',
                    "Server-detected file type '{$sniffedMime}' is not permitted."
                );
            }

            if ($sniffedMime !== $declaredMime) {
                return $this->fail(
                    'mime_mismatch',
                    "Declared MIME '{$declaredMime}' does not match server-detected '{$sniffedMime}'."
                );
            }

            $authoritativeMime = $sniffedMime;
        }

        // Size cap, keyed off the authoritative type.
        $size     = filesize($tempPath);
        $maxBytes = in_array($authoritativeMime, self::LARGE_MIME_TYPES, true)
            ? self::VIDEO_MAX_BYTES
            : self::IMAGE_DOC_MAX_BYTES;

        if ($size > $maxBytes) {
            $maxMB = $maxBytes / 1024 / 1024;
            return $this->fail('file_too_large', "File exceeds maximum size of {$maxMB}MB for type {$authoritativeMime}.");
        }

        // Magic-byte signature check against the authoritative MIME.
        $handle = fopen($tempPath, 'rb');
        if ($handle === false) {
            return $this->fail('file_unreadable', 'Cannot read the uploaded file.');
        }

        $header = fread($handle, 12);
        fclose($handle);

        if ($header === false || strlen($header) < 4) {
            return $this->fail('file_too_small', 'File is too small to determine type.');
        }

        $magicResult = $this->checkMagicBytes($header, $authoritativeMime);
        if (! $magicResult['valid']) {
            return $magicResult;
        }

        $sha256 = hash_file('sha256', $tempPath);

        return [
            'valid'        => true,
            'reason_code'  => '',
            'reason'       => '',
            'sha256'       => $sha256,
            'sniffed_mime' => $authoritativeMime,
        ];
    }

    /**
     * Sniff the MIME type via finfo. Returns null if finfo is unavailable.
     */
    private function sniffMime(string $path): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        if ($mime === false || $mime === '') {
            return null;
        }

        $mime = strtolower(trim(explode(';', $mime)[0]));

        return self::MIME_ALIASES[$mime] ?? $mime;
    }

    /**
     * Magic-byte signature check for a given MIME type.
     */
    private function checkMagicBytes(string $header, string $mime): array
    {
        $bytes = bin2hex($header);

        switch ($mime) {
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
                if (strlen($header) >= 8 && substr($bytes, 8, 8) === '66747970') {
                    return $this->ok();
                }
                return $this->fail('magic_mismatch', 'File does not appear to be a valid MP4 video file.');

            default:
                return $this->fail('mime_not_allowed', "Unrecognized MIME type '{$mime}'.");
        }
    }

    private function ok(): array
    {
        return ['valid' => true, 'reason_code' => '', 'reason' => '', 'sha256' => null, 'sniffed_mime' => null];
    }

    private function fail(string $reasonCode, string $reason): array
    {
        return ['valid' => false, 'reason_code' => $reasonCode, 'reason' => $reason, 'sha256' => null, 'sniffed_mime' => null];
    }
}
