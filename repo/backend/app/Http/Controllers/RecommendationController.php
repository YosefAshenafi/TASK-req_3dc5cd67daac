<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\FeatureFlag;
use App\Models\RecommendationCandidate;
use App\Services\Monitoring\MetricsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecommendationController extends Controller
{
    public function __construct(private readonly MetricsRecorder $metrics) {}

    /**
     * GET /api/recommendations - Return up to 25 recommendation candidates for the user.
     *
     * Honors the same degradation policy as `GET /api/search?sort=recommended`: when the
     * `recommended_enabled` feature flag is off (tripped by the circuit breaker), this
     * endpoint falls back to a `most_played` ranking and sets the
     * `X-Recommendation-Degraded: true` response header.
     */
    public function index(Request $request): JsonResponse
    {
        $flag     = FeatureFlag::find('recommended_enabled');
        $degraded = ! $flag || ! $flag->enabled;

        $this->metrics->incrementRecommendationRequests();

        if ($degraded) {
            $items = $this->mostPlayedFallback();

            $response = response()->json([
                'items'    => $items,
                'degraded' => true,
                'fallback' => 'most_played',
            ]);
            $response->header('X-Recommendation-Degraded', 'true');
            return $response;
        }

        $candidates = RecommendationCandidate::with('asset')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('score')
            ->limit(25)
            ->get();

        $items = $candidates->map(fn ($c) => [
            'asset_id'     => $c->asset_id,
            'score'        => $c->score,
            'reason_tags'  => is_array($c->reason_tags_json)
                ? $c->reason_tags_json
                : (json_decode($c->reason_tags_json ?? '[]', true) ?? []),
            'refreshed_at' => $c->refreshed_at?->toIso8601String(),
            'asset'        => $c->asset ? [
                'id'     => $c->asset->id,
                'title'  => $c->asset->title,
                'mime'   => $c->asset->mime,
                'status' => $c->asset->status,
            ] : null,
        ])->values();

        if ($items->isNotEmpty()) {
            $this->metrics->incrementRecommendationHits();
        }

        return response()->json([
            'items'    => $items,
            'degraded' => false,
            'fallback' => null,
        ]);
    }

    private function mostPlayedFallback(): array
    {
        $rows = Asset::query()
            ->where('assets.status', 'ready')
            ->leftJoin(
                DB::raw('(SELECT asset_id, COUNT(*) as play_count FROM play_history GROUP BY asset_id) as ph_counts'),
                'assets.id',
                '=',
                'ph_counts.asset_id'
            )
            ->orderByDesc(DB::raw('COALESCE(ph_counts.play_count, 0)'))
            ->orderByDesc('assets.id')
            ->limit(25)
            ->select('assets.*')
            ->get();

        return $rows->map(fn ($a) => [
            'asset_id'     => $a->id,
            'score'        => 0,
            'reason_tags'  => [],
            'refreshed_at' => null,
            'asset'        => [
                'id'     => $a->id,
                'title'  => $a->title,
                'mime'   => $a->mime,
                'status' => $a->status,
            ],
        ])->values()->all();
    }
}
