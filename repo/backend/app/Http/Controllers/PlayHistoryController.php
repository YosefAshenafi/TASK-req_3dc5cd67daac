<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AssetResource;
use App\Jobs\ComputeUserRecommendationsJob;
use App\Models\Asset;
use App\Models\PlayHistory;
use App\Models\RecommendationScore;
use App\Services\DegradationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayHistoryController extends Controller
{
    public function __construct(private readonly DegradationService $degradation) {}

    public function index(Request $request): JsonResponse
    {
        $history = PlayHistory::with('asset')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('played_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $history->map(fn (PlayHistory $h) => [
                'id' => $h->id,
                'played_at' => $h->played_at->toIso8601String(),
                'asset' => new AssetResource($h->asset),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
        ]);

        $entry = PlayHistory::create([
            'user_id' => $request->user()->id,
            'asset_id' => $data['asset_id'],
            'played_at' => now(),
        ]);

        Asset::where('id', $data['asset_id'])->increment('play_count');

        $isRecommended = RecommendationScore::where('user_id', $request->user()->id)
            ->where('asset_id', $data['asset_id'])
            ->orderByDesc('score')
            ->limit(20)
            ->exists();

        if ($isRecommended) {
            $this->degradation->recordHit();
        }

        ComputeUserRecommendationsJob::dispatch($request->user()->id);

        return response()->json([
            'data' => ['id' => $entry->id, 'played_at' => $entry->played_at->toIso8601String()],
        ], 201);
    }
}
