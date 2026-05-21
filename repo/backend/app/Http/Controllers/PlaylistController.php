<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PlaylistResource;
use App\Models\Asset;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlaylistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $playlists = Playlist::with('items.asset')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json(['data' => PlaylistResource::collection($playlists)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $shareCode = $this->generateShareCode();

        if ($shareCode === null) {
            return response()->json([
                'message' => 'Could not generate a unique share code. Please try again.',
                'errors' => [],
            ], 503);
        }

        $playlist = Playlist::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'share_code' => $shareCode,
        ]);

        return response()->json(['data' => new PlaylistResource($playlist)], 201);
    }

    public function show(Request $request, Playlist $playlist): JsonResponse
    {
        if ($playlist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.', 'errors' => []], 403);
        }

        $playlist->load('items.asset');

        return response()->json(['data' => new PlaylistResource($playlist)]);
    }

    public function update(Request $request, Playlist $playlist): JsonResponse
    {
        if ($playlist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.', 'errors' => []], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $playlist->update($data);

        return response()->json(['data' => new PlaylistResource($playlist->fresh())]);
    }

    public function destroy(Request $request, Playlist $playlist): JsonResponse
    {
        if ($playlist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.', 'errors' => []], 403);
        }

        $playlist->delete();

        return response()->json(null, 204);
    }

    public function addItem(Request $request, Playlist $playlist): JsonResponse
    {
        if ($playlist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.', 'errors' => []], 403);
        }

        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $position = $data['position'] ?? $playlist->items()->max('position') + 1;

        $item = PlaylistItem::create([
            'playlist_id' => $playlist->id,
            'asset_id' => $data['asset_id'],
            'position' => $position,
        ]);

        return response()->json(['data' => [
            'item_id' => $item->id,
            'playlist_id' => $playlist->id,
            'asset_id' => $item->asset_id,
            'position' => $item->position,
        ]], 201);
    }

    public function removeItem(Request $request, Playlist $playlist, PlaylistItem $item): JsonResponse
    {
        if ($playlist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.', 'errors' => []], 403);
        }

        if ($item->playlist_id !== $playlist->id) {
            return response()->json(['message' => 'Resource not found.', 'errors' => []], 404);
        }

        $item->delete();

        return response()->json(null, 204);
    }

    protected function generateShareCode(): ?string
    {
        for ($i = 0; $i < 10; $i++) {
            $candidate = strtoupper(Str::random(8));
            if (!Playlist::where('share_code', $candidate)->exists()) {
                return $candidate;
            }
        }
        return null;
    }

    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'share_code' => ['required', 'string', 'max:8'],
        ]);

        $playlist = Playlist::with('items.asset')
            ->where('share_code', $data['share_code'])
            ->first();

        if ($playlist === null) {
            return response()->json(['message' => 'Invalid share code.', 'errors' => []], 404);
        }

        return response()->json(['data' => ['playlist' => new PlaylistResource($playlist)]]);
    }
}
