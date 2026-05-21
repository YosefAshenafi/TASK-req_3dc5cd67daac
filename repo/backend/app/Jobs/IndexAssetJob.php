<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Asset;
use App\Models\AssetSearchIndex;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(private readonly int $assetId) {}

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);

        if ($asset === null) {
            return;
        }

        $searchText = implode(' ', array_filter([
            $asset->title,
            $asset->description,
            implode(' ', $asset->tags ?? []),
        ]));

        AssetSearchIndex::updateOrCreate(
            ['asset_id' => $asset->id],
            ['search_text' => $searchText]
        );
    }
}
