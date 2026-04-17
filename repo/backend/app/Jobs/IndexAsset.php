<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\SearchIndex;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $assetId
    ) {}

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            Log::warning("IndexAsset: Asset {$this->assetId} not found.");
            return;
        }

        $tokenizedTitle = $this->tokenize($asset->title ?? '');
        $tokenizedBody  = $this->tokenize($asset->description ?? '');

        SearchIndex::updateOrCreate(
            ['asset_id' => $this->assetId],
            [
                'tokenized_title' => $tokenizedTitle,
                'tokenized_body'  => $tokenizedBody,
                'weight_tsv'      => null,
            ]
        );

        Log::info("IndexAsset: Indexed asset {$this->assetId}.");
    }

    /**
     * Tokenize text: lowercase, strip non-alphanumeric, split on whitespace.
     */
    private function tokenize(string $text): string
    {
        $text = mb_strtolower($text);
        // Replace non-alphanumeric (except spaces) with space
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text;
    }
}
