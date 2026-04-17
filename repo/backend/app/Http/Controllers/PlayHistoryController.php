<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\PlayHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayHistoryController extends Controller
{
    /**
     * POST /api/assets/{id}/play - Record a play event.
     */
    public function play(Request $request, string $id): JsonResponse
    {
        Asset::findOrFail($id);

        $request->validate([
            'session_id' => ['nullable', 'string', 'max:255'],
            'context'    => ['nullable', 'string', 'max:255'],
        ]);

        PlayHistory::create([
            'user_id'    => $request->user()->id,
            'asset_id'   => $id,
            'played_at'  => now(),
            'session_id' => $request->input('session_id'),
            'context'    => $request->input('context'),
        ]);

        return response()->json(['message' => 'Play recorded.'], 202);
    }

    /**
     * GET /api/history - Get the authenticated user's play history.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 100);

        $history = PlayHistory::with('asset')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('played_at')
            ->limit($perPage + 1)
            ->get();

        $hasMore    = $history->count() > $perPage;
        $history    = $history->take($perPage);
        $nextCursor = $hasMore ? (string) $history->last()?->id : null;

        return response()->json([
            'items' => $history->map(fn ($h) => [
                'id'        => $h->id,
                'asset_id'  => $h->asset_id,
                'played_at' => $h->played_at?->toIso8601String(),
                'asset'     => $h->asset ? [
                    'id'    => $h->asset->id,
                    'title' => $h->asset->title,
                    'mime'  => $h->asset->mime,
                ] : null,
            ]),
            'next_cursor' => $nextCursor,
        ]);
    }

    /**
     * GET /api/now-playing - Return current + recent plays for the user.
     */
    public function nowPlaying(Request $request): JsonResponse
    {
        $recent = PlayHistory::with('asset')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('played_at')
            ->limit(10)
            ->get();

        $current = $recent->first();
        $recentItems = $recent->skip(1)->values();

        return response()->json([
            'current' => $current ? [
                'id'        => $current->id,
                'asset_id'  => $current->asset_id,
                'played_at' => $current->played_at?->toIso8601String(),
                'asset'     => $current->asset ? [
                    'id'    => $current->asset->id,
                    'title' => $current->asset->title,
                    'mime'  => $current->asset->mime,
                ] : null,
            ] : null,
            'recent' => $recentItems->map(fn ($h) => [
                'id'        => $h->id,
                'asset_id'  => $h->asset_id,
                'played_at' => $h->played_at?->toIso8601String(),
                'asset'     => $h->asset ? [
                    'id'    => $h->asset->id,
                    'title' => $h->asset->title,
                    'mime'  => $h->asset->mime,
                ] : null,
            ]),
        ]);
    }
}
