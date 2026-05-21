<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\Favorite;
use App\Models\RecommendationScore;
use App\Services\DegradationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(private readonly DegradationService $degradation) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        if ($this->degradation->isDisabled()) {
            return $this->mostPlayedFallback();
        }

        $startTime = microtime(true);

        $scores = RecommendationScore::with('asset')
            ->where('user_id', $userId)
            ->orderByDesc('score')
            ->limit(20)
            ->get();

        $latency = (microtime(true) - $startTime) * 1000;
        $this->degradation->recordLatency($latency);

        if ($scores->isEmpty()) {
            return $this->mostPlayedFallback();
        }

        $this->degradation->recordServed($scores->count());

        $favoriteTags = $this->getUserFavoriteTags($userId);

        $data = $scores->map(fn (RecommendationScore $rs) => array_merge(
            (new AssetResource($rs->asset))->resolve($request),
            [
                'recommendation_reason' => $this->buildReasonFromAffinity($rs->asset, $favoriteTags),
                'recommendation_score' => $rs->score,
            ]
        ));

        return response()->json(['data' => $data]);
    }

    private function getUserFavoriteTags(int $userId): array
    {
        return Favorite::with('asset')
            ->where('user_id', $userId)
            ->get()
            ->flatMap(fn (Favorite $f) => $f->asset?->tags ?? [])
            ->countBy()
            ->all();
    }

    private function buildReasonFromAffinity(Asset $asset, array $favoriteTags): string
    {
        $assetTags = $asset->tags ?? [];
        $overlap = array_values(array_intersect($assetTags, array_keys($favoriteTags)));

        if (!empty($overlap)) {
            $formatted = array_map(
                fn (string $t) => ucwords(str_replace(['-', '_'], ' ', $t)),
                array_slice($overlap, 0, 3)
            );
            return 'Based on your favorites: ' . implode(', ', $formatted);
        }

        return 'Based on your listening history';
    }

    private function mostPlayedFallback(): JsonResponse
    {
        $assets = Asset::where('status', 'approved')
            ->orderByDesc('play_count')
            ->limit(20)
            ->get();

        $data = $assets->map(fn (Asset $a) => array_merge(
            (new AssetResource($a))->resolve(request()),
            ['recommendation_reason' => 'Popular in your facility', 'recommendation_score' => null]
        ));

        return response()->json(['data' => $data, 'fallback' => true]);
    }
}
