<?php

/**
 * Write a JPEG-shaped temp file larger than the image/doc cap (25MB) without
 * holding the full payload in a single PHP string (avoids OOM under PCOV / low memory_limit).
 */
function write_oversized_jpeg_temp_path(int $payloadBytes = 26 * 1024 * 1024): string
{
    $path = tempnam(sys_get_temp_dir(), 'sp_big_jpeg_') . '.jpg';
    $fh = fopen($path, 'wb');
    fwrite($fh, "\xFF\xD8\xFF\xE0");

    $chunkSize = 1024 * 1024;
    $chunk = str_repeat('x', $chunkSize);
    $written = 0;
    while ($written < $payloadBytes) {
        $n = min($chunkSize, $payloadBytes - $written);
        fwrite($fh, $n === $chunkSize ? $chunk : substr($chunk, 0, $n));
        $written += $n;
    }
    fclose($fh);

    return $path;
}
