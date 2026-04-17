<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * GET /api/favorites - Return the authenticated user's favorites.
     */
    public function index(Request $request): JsonResponse
    {
        $favorites = Favorite::with('asset')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'items' => $favorites->map(fn ($f) => [
                'asset_id'   => $f->asset_id,
                'created_at' => $f->created_at?->toIso8601String(),
                'asset'      => $f->asset ? [
                    'id'     => $f->asset->id,
                    'title'  => $f->asset->title,
                    'mime'   => $f->asset->mime,
                    'status' => $f->asset->status,
                ] : null,
            ]),
            'next_cursor' => null,
        ]);
    }

    /**
     * PUT /api/favorites/{asset_id} - Idempotently add an asset to favorites.
     */
    public function update(Request $request, string $assetId): JsonResponse
    {
        Asset::findOrFail($assetId);

        Favorite::firstOrCreate([
            'user_id'  => $request->user()->id,
            'asset_id' => $assetId,
        ], [
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Added to favorites.'], 200);
    }

    /**
     * DELETE /api/favorites/{asset_id} - Idempotently remove an asset from favorites.
     */
    public function destroy(Request $request, string $assetId): JsonResponse
    {
        Favorite::where('user_id', $request->user()->id)
            ->where('asset_id', $assetId)
            ->delete();

        return response()->json(null, 204);
    }
}
