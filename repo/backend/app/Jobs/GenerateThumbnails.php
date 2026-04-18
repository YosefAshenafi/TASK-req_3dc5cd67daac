<?php

namespace App\Jobs;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class GenerateThumbnails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const WIDTHS = [160, 480, 960];

    public function __construct(
        private readonly int $assetId
    ) {}

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            Log::warning("GenerateThumbnails: Asset {$this->assetId} not found.");
            return;
        }

        // Only process image MIME types for thumbnails
        if (! str_starts_with($asset->mime, 'image/')) {
            Log::info("GenerateThumbnails: Skipping non-image asset {$this->assetId} ({$asset->mime}).");
            $asset->update(['status' => 'ready']);
            return;
        }

        $sourcePath = Storage::disk('local')->path($asset->file_path);

        if (! file_exists($sourcePath)) {
            Log::error("GenerateThumbnails: Source file not found at {$sourcePath} for asset {$this->assetId}.");
            $asset->update(['status' => 'failed']);
            return;
        }

        $thumbnailPaths = [];

        foreach (self::WIDTHS as $width) {
            try {
                $thumbRelPath = "thumbs/{$this->assetId}/{$width}.jpg";
                $outPath      = Storage::disk('public')->path($thumbRelPath);

                @mkdir(dirname($outPath), 0755, true);

                Image::read($sourcePath)
                    ->scaleDown($width)
                    ->toJpeg(85)
                    ->save($outPath);

                $thumbnailPaths[(string) $width] = $thumbRelPath;

                Log::info("GenerateThumbnails: Created {$width}px thumbnail for asset {$this->assetId}.");
            } catch (\Throwable $e) {
                Log::error("GenerateThumbnails: Failed to create {$width}px thumbnail for asset {$this->assetId}: {$e->getMessage()}");
            }
        }

        $updates = ['status' => 'ready'];
        if (! empty($thumbnailPaths)) {
            $updates['thumbnail_urls'] = $thumbnailPaths;
        }

        $asset->update($updates);
    }
}
