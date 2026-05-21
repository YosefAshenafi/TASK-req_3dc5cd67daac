<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;

class MimeValidationService
{
    private const ALLOWED_MIMES = [
        'image/jpeg' => 25 * 1024 * 1024,
        'image/png' => 25 * 1024 * 1024,
        'application/pdf' => 25 * 1024 * 1024,
        'audio/mpeg' => 25 * 1024 * 1024,
        'video/mp4' => 250 * 1024 * 1024,
    ];

    private const BLOCKED_MAGIC_BYTES = [
        "\x7FELF",
        'MZ',
        "\xCE\xFA\xED\xFE",
        "\xCF\xFA\xED\xFE",
        "\xFE\xED\xFA\xCE",
        "\xFE\xED\xFA\xCF",
    ];

    public function validate(UploadedFile $file): array
    {
        $magic = $this->readMagicBytes($file->getPathname(), 16);

        foreach (self::BLOCKED_MAGIC_BYTES as $blocked) {
            if (str_starts_with($magic, $blocked)) {
                return ['valid' => false, 'error' => 'Executable file type is not permitted.', 'mime_type' => null];
            }
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file->getPathname());
        finfo_close($finfo);

        if ($detectedMime === false || !isset(self::ALLOWED_MIMES[$detectedMime])) {
            return [
                'valid' => false,
                'error' => 'File type not permitted. Allowed: JPEG, PNG, PDF, MP3, MP4.',
                'mime_type' => null,
            ];
        }

        $sizeLimit = self::ALLOWED_MIMES[$detectedMime];
        if ($file->getSize() > $sizeLimit) {
            $limitMb = $sizeLimit / (1024 * 1024);
            return [
                'valid' => false,
                'error' => "File exceeds the {$limitMb}MB limit for this type.",
                'mime_type' => null,
            ];
        }

        return ['valid' => true, 'error' => null, 'mime_type' => $detectedMime];
    }

    private function readMagicBytes(string $path, int $bytes): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        $data = fread($handle, $bytes);
        fclose($handle);
        return $data !== false ? $data : '';
    }
}
