<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class GenerateThumbnailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(private readonly int $assetId) {}

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);

        if ($asset === null) {
            return;
        }

        if (!in_array($asset->mime_type, ['image/jpeg', 'image/png'], true)) {
            return;
        }

        $sourcePath = Storage::disk('local')->path($asset->file_path);

        if (!file_exists($sourcePath)) {
            Log::warning('thumbnail_source_missing', ['asset_id' => $this->assetId]);
            return;
        }

        $manager = new ImageManager(new Driver());
        $updates = [];

        foreach ([160, 480, 960] as $width) {
            $thumbPath = 'media/thumb_' . $width . '_' . basename($asset->file_path);
            $fullThumbPath = Storage::disk('local')->path($thumbPath);

            $manager->read($sourcePath)
                ->scale(width: $width)
                ->toJpeg(85)
                ->save($fullThumbPath);

            $updates['thumbnail_' . $width] = $thumbPath;
        }

        $asset->update($updates);
    }
}
