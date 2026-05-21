<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessFileDropEvents extends Command
{
    protected $signature = 'app:process-file-drop';
    protected $description = 'Process any device events delivered via file drop folder';

    public function handle(): int
    {
        $dropDir = storage_path('app/device_drop');

        if (!is_dir($dropDir)) {
            return self::SUCCESS;
        }

        $files = glob($dropDir . '/*.json') ?: [];

        foreach ($files as $file) {
            try {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }
                $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                Log::info('file_drop_event_processed', ['file' => basename($file), 'event_type' => $payload['event_type'] ?? 'unknown']);
                unlink($file);
            } catch (\Throwable $e) {
                Log::warning('file_drop_event_failed', ['file' => basename($file), 'error' => $e->getMessage()]);
            }
        }

        return self::SUCCESS;
    }
}
