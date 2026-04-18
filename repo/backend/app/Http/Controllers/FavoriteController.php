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
     *
     * If a previously-favorited asset later becomes non-ready (upload still processing,
     * failed re-encode, or retracted by an admin), its title/MIME are scrubbed for
     * non-admins so the favorites view does not act as a back-channel to unpublished
     * asset metadata.
     */
    public function index(Request $request): JsonResponse
    {
        $favorites = Favorite::with('asset')
            ->where('user_id', $request->user()->id)
            ->get();

        $isAdmin = $request->user()?->role === 'admin';

        return response()->json([
            'items' => $favorites->map(function ($f) use ($isAdmin) {
                if (! $f->asset) {
                    return [
                        'asset_id'   => $f->asset_id,
                        'created_at' => $f->created_at?->toIso8601String(),
                        'asset'      => null,
                    ];
                }

                $isReady = $f->asset->status === 'ready';
                $exposeMeta = $isAdmin || $isReady;

                return [
                    'asset_id'   => $f->asset_id,
                    'created_at' => $f->created_at?->toIso8601String(),
                    'asset'      => [
                        'id'     => $f->asset->id,
                        'title'  => $exposeMeta ? $f->asset->title : null,
                        'mime'   => $exposeMeta ? $f->asset->mime  : null,
                        'status' => $isAdmin ? $f->asset->status   : ($isReady ? 'ready' : 'unavailable'),
                    ],
                ];
            }),
            'next_cursor' => null,
        ]);
    }

    /**
     * PUT /api/favorites/{asset_id} - Idempotently add an asset to favorites.
     *
     * Only assets with status='ready' are favoritable by non-admin users. Non-ready assets
     * are still under admin review and must not be surfaced through the user-visible
     * discovery / history / playlist surfaces.
     */
    public function update(Request $request, string $assetId): JsonResponse
    {
        $asset   = Asset::findOrFail($assetId);
        $isAdmin = $request->user()?->role === 'admin';

        if (! $isAdmin && $asset->status !== 'ready') {
            // Return 404 so we don't leak the existence of unpublished assets.
            return response()->json(['message' => 'Asset not found.'], 404);
        }

        $favorite = Favorite::firstOrCreate([
            'user_id'  => $request->user()->id,
            'asset_id' => $asset->id,
        ], [
            'created_at' => now(),
        ]);

        return response()->json([
            'user_id'    => $favorite->user_id,
            'asset_id'   => $favorite->asset_id,
            'created_at' => $favorite->created_at?->toIso8601String(),
        ], 200);
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
