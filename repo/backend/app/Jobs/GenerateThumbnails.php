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
            return;
        }

        $sourcePath = Storage::path($asset->file_path);

        if (! file_exists($sourcePath)) {
            Log::error("GenerateThumbnails: Source file not found at {$sourcePath} for asset {$this->assetId}.");
            $asset->update(['status' => 'failed']);
            return;
        }

        $thumbBaseDir = "media/thumbs/{$this->assetId}";
        Storage::makeDirectory($thumbBaseDir);

        foreach (self::WIDTHS as $width) {
            try {
                $outPath = Storage::path("{$thumbBaseDir}/{$width}.jpg");

                Image::read($sourcePath)
                    ->scaleDown($width)
                    ->toJpeg(85)
                    ->save($outPath);

                Log::info("GenerateThumbnails: Created {$width}px thumbnail for asset {$this->assetId}.");
            } catch (\Throwable $e) {
                Log::error("GenerateThumbnails: Failed to create {$width}px thumbnail for asset {$this->assetId}: {$e->getMessage()}");
            }
        }

        $asset->update(['status' => 'ready']);
    }
}
