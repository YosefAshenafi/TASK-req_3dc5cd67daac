<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = Favorite::with('asset')
            ->where('user_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->get();

        $assets = $favorites->map(fn (Favorite $f) => [
            'favorite_id' => $f->id,
            'asset' => new AssetResource($f->asset),
        ]);

        return response()->json(['data' => $assets]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
        ]);

        $existing = Favorite::withTrashed()
            ->where('user_id', $request->user()->id)
            ->where('asset_id', $request->input('asset_id'))
            ->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
                return response()->json(['data' => ['favorite_id' => $existing->id]], 201);
            }
            return response()->json(['data' => ['favorite_id' => $existing->id]], 200);
        }

        $favorite = Favorite::create([
            'user_id' => $request->user()->id,
            'asset_id' => $request->input('asset_id'),
        ]);

        return response()->json(['data' => ['favorite_id' => $favorite->id]], 201);
    }

    public function destroy(Request $request, Favorite $favorite): JsonResponse
    {
        if ($favorite->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.', 'errors' => []], 403);
        }

        $favorite->delete();

        return response()->json(null, 204);
    }
}
