<?php

namespace App\Jobs;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Extension hook for the on-prem AV / content scanner documented in CLAUDE.md.
 *
 * Today this is a no-op that records the scan request in the log stream so the upload
 * pipeline has an explicit, dispatchable extension point. A real on-prem scanner
 * (ClamAV, MediaConch, operator-owned plugin, etc.) can be wired in by replacing the
 * handle() body — the caller contract (asset id → queued scan) stays the same.
 */
class MediaScanRequested implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $assetId
    ) {}

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            Log::warning("MediaScanRequested: Asset {$this->assetId} not found.");
            return;
        }

        Log::info('MediaScanRequested: scan queued for asset', [
            'asset_id'  => $asset->id,
            'mime'      => $asset->mime,
            'file_path' => $asset->file_path,
            'note'      => 'stub — wire an on-prem AV/content scanner here',
        ]);
    }
}
