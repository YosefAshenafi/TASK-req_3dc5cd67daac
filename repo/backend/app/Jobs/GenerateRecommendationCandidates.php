<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\AssetTag;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\RecommendationCandidate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateRecommendationCandidates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?int $userId = null
    ) {}

    public function handle(): void
    {
        $users = $this->userId
            ? User::where('id', $this->userId)->get()
            : User::whereNull('blacklisted_at')->whereNull('deleted_at')->get();

        foreach ($users as $user) {
            try {
                $this->generateForUser($user);
            } catch (\Throwable $e) {
                Log::error("GenerateRecommendationCandidates: Error for user {$user->id}: {$e->getMessage()}");
            }
        }
    }

    private function generateForUser(User $user): void
    {
        // Gather tags from user's favorites and play history
        $favoritedAssetIds = Favorite::where('user_id', $user->id)->pluck('asset_id')->toArray();
        $playedAssetIds    = PlayHistory::where('user_id', $user->id)
            ->pluck('asset_id')
            ->unique()
            ->toArray();

        $userAssetIds = array_unique(array_merge($favoritedAssetIds, $playedAssetIds));

        if (empty($userAssetIds)) {
            return;
        }

        // Build a tag frequency map from user's known assets
        $userTagCounts = AssetTag::whereIn('asset_id', $userAssetIds)
            ->select('tag', DB::raw('COUNT(*) as cnt'))
            ->groupBy('tag')
            ->pluck('cnt', 'tag')
            ->toArray();

        if (empty($userTagCounts)) {
            return;
        }

        // Compute user vector magnitude
        $userMagnitude = sqrt(array_sum(array_map(fn ($v) => $v * $v, $userTagCounts)));

        // Get all ready assets not already in user's list
        $candidateAssets = Asset::where('status', 'ready')
            ->whereNotIn('id', $userAssetIds)
            ->with('assetTags')
            ->get();

        $recommendations = [];

        foreach ($candidateAssets as $asset) {
            $assetTags = $asset->assetTags->pluck('tag')->toArray();

            if (empty($assetTags)) {
                continue;
            }

            // Compute tag overlap (cosine similarity)
            $dotProduct    = 0;
            $assetMagnitude = sqrt(count($assetTags));

            foreach ($assetTags as $tag) {
                if (isset($userTagCounts[$tag])) {
                    $dotProduct += $userTagCounts[$tag];
                }
            }

            if ($dotProduct === 0 || $userMagnitude === 0.0 || $assetMagnitude === 0.0) {
                continue;
            }

            $cosineSim = $dotProduct / ($userMagnitude * $assetMagnitude);

            // Build reason tags (shared tags sorted by weight)
            $reasonTags = [];
            foreach ($assetTags as $tag) {
                if (isset($userTagCounts[$tag])) {
                    $reasonTags[] = $tag;
                }
            }

            $recommendations[] = [
                'user_id'          => $user->id,
                'asset_id'         => $asset->id,
                'score'            => round($cosineSim, 4),
                'reason_tags_json' => json_encode($reasonTags),
                'refreshed_at'     => now(),
            ];
        }

        // Sort by score desc, keep top 50
        usort($recommendations, fn ($a, $b) => $b['score'] <=> $a['score']);
        $recommendations = array_slice($recommendations, 0, 50);

        // Upsert
        foreach ($recommendations as $rec) {
            RecommendationCandidate::updateOrCreate(
                ['user_id' => $rec['user_id'], 'asset_id' => $rec['asset_id']],
                [
                    'score'            => $rec['score'],
                    'reason_tags_json' => $rec['reason_tags_json'],
                    'refreshed_at'     => $rec['refreshed_at'],
                ]
            );
        }

        Log::info("GenerateRecommendationCandidates: Generated " . count($recommendations) . " candidates for user {$user->id}.");
    }
}
