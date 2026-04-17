<?php

namespace App\Http\Controllers;

use App\Models\RecommendationCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * GET /api/recommendations - Return up to 25 recommendation candidates for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $candidates = RecommendationCandidate::with('asset')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('score')
            ->limit(25)
            ->get();

        return response()->json([
            'items' => $candidates->map(fn ($c) => [
                'asset_id'    => $c->asset_id,
                'score'       => $c->score,
                'reason_tags' => $c->reason_tags_json ?? [],
                'refreshed_at' => $c->refreshed_at?->toIso8601String(),
                'asset'       => $c->asset ? [
                    'id'    => $c->asset->id,
                    'title' => $c->asset->title,
                    'mime'  => $c->asset->mime,
                    'status' => $c->asset->status,
                ] : null,
            ]),
        ]);
    }
}
