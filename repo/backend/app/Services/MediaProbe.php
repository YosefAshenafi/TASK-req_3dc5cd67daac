<?php

namespace App\Services;

class MediaProbe
{
    /**
     * Return duration in whole seconds for audio/video files via ffprobe.
     * Returns null for non-audio/video MIME types or on extraction failure.
     */
    public function getDurationSeconds(string $filePath, string $mime): ?int
    {
        if (! str_starts_with($mime, 'audio/') && ! str_starts_with($mime, 'video/')) {
            return null;
        }

        $output = @shell_exec(
            'ffprobe -v quiet -show_entries format=duration -of json ' . escapeshellarg($filePath) . ' 2>/dev/null'
        );

        if (! $output) {
            return null;
        }

        $data = json_decode($output, true);

        if (! isset($data['format']['duration'])) {
            return null;
        }

        return max(1, (int) round((float) $data['format']['duration']));
    }
}
