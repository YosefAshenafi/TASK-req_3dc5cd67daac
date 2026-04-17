<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class PlaylistController extends Controller
{
    private const SHARE_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * GET /api/playlists - List user's playlists.
     */
    public function index(Request $request): JsonResponse
    {
        $playlists = Playlist::where('owner_id', $request->user()->id)
            ->withCount('items')
            ->get();

        return response()->json($playlists);
    }

    /**
     * POST /api/playlists - Create a new playlist.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $playlist = Playlist::create([
            'owner_id' => $request->user()->id,
            'name'     => $request->input('name'),
        ]);

        return response()->json([
            'id'         => $playlist->id,
            'name'       => $playlist->name,
            'owner_id'   => $playlist->owner_id,
            'created_at' => $playlist->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * GET /api/playlists/{id} - Get a playlist with its items.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $playlist = Playlist::with(['items.asset'])
            ->where(function ($q) use ($request) {
                $q->where('owner_id', $request->user()->id);
            })
            ->findOrFail($id);

        return response()->json([
            'id'         => $playlist->id,
            'name'       => $playlist->name,
            'owner_id'   => $playlist->owner_id,
            'created_at' => $playlist->created_at?->toIso8601String(),
            'items'      => $playlist->items->map(fn ($item) => [
                'id'       => $item->id,
                'position' => $item->position,
                'asset_id' => $item->asset_id,
                'asset'    => $item->asset ? [
                    'id'     => $item->asset->id,
                    'title'  => $item->asset->title,
                    'mime'   => $item->asset->mime,
                    'status' => $item->asset->status,
                ] : null,
            ]),
        ]);
    }

    /**
     * PATCH /api/playlists/{id} - Update playlist name and/or reorder items.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'item_order'  => ['sometimes', 'array'],
            'item_order.*' => ['integer'],
        ]);

        $playlist = Playlist::where('owner_id', $request->user()->id)->findOrFail($id);

        if ($request->filled('name')) {
            $playlist->name = $request->input('name');
            $playlist->save();
        }

        // Reorder items if item_order provided (array of item IDs in desired order)
        if ($request->has('item_order')) {
            $order = $request->input('item_order');
            foreach ($order as $position => $itemId) {
                PlaylistItem::where('playlist_id', $playlist->id)
                    ->where('id', $itemId)
                    ->update(['position' => $position + 1]);
            }
        }

        return response()->json([
            'id'   => $playlist->id,
            'name' => $playlist->name,
        ]);
    }

    /**
     * DELETE /api/playlists/{id} - Soft delete a playlist.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $playlist = Playlist::where('owner_id', $request->user()->id)->findOrFail($id);
        $playlist->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/playlists/{id}/share - Generate a share code.
     * Rate limited to 5 per hour per user.
     */
    public function share(Request $request, string $id): JsonResponse
    {
        $playlist = Playlist::where('owner_id', $request->user()->id)->findOrFail($id);

        $throttleKey = 'playlist_share:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message'     => 'Rate limit exceeded. You can generate at most 5 share codes per hour.',
                'retry_after' => $seconds,
            ], 429);
        }

        $request->validate([
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        RateLimiter::hit($throttleKey, 60 * 60);

        $code      = $this->generateShareCode();
        $expiresAt = $request->filled('expires_in_hours')
            ? now()->addHours($request->input('expires_in_hours'))
            : now()->addDays(7);

        $share = PlaylistShare::create([
            'playlist_id' => $playlist->id,
            'code'        => $code,
            'created_by'  => $request->user()->id,
            'expires_at'  => $expiresAt,
        ]);

        return response()->json([
            'id'         => $share->id,
            'code'       => $share->code,
            'expires_at' => $share->expires_at?->toIso8601String(),
        ], 201);
    }

    /**
     * POST /api/playlists/redeem - Redeem a share code and clone the playlist.
     */
    public function redeem(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:8'],
        ]);

        $share = PlaylistShare::with(['playlist.items', 'playlist.owner'])
            ->where('code', strtoupper($request->input('code')))
            ->first();

        if (! $share) {
            return response()->json(['message' => 'Invalid share code.'], 404);
        }

        if ($share->revoked_at !== null) {
            return response()->json(['message' => 'This share code has been revoked.'], 410);
        }

        if ($share->expires_at !== null && $share->expires_at->isPast()) {
            return response()->json(['message' => 'This share code has expired.'], 410);
        }

        // Clone the playlist
        $originalPlaylist = $share->playlist;

        // Check owner eligibility
        $owner = $originalPlaylist->owner;
        if (! $owner || $owner->trashed() || in_array($owner->status, ['blacklisted', 'frozen'])) {
            return response()->json(['message' => 'Playlist owner is not eligible for sharing.'], 403);
        }

        $newPlaylist = DB::transaction(function () use ($request, $originalPlaylist) {
            $cloned = Playlist::create([
                'owner_id' => $request->user()->id,
                'name'     => $originalPlaylist->name . ' (shared)',
            ]);

            foreach ($originalPlaylist->items as $item) {
                PlaylistItem::create([
                    'playlist_id' => $cloned->id,
                    'asset_id'    => $item->asset_id,
                    'position'    => $item->position,
                ]);
            }

            return $cloned;
        });

        return response()->json([
            'id'         => $newPlaylist->id,
            'name'       => $newPlaylist->name,
            'owner_id'   => $newPlaylist->owner_id,
            'created_at' => $newPlaylist->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * POST /api/playlists/{id}/items - Add an item to a playlist.
     */
    public function addItem(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'asset_id' => ['required', 'integer'],
        ]);

        $playlist = Playlist::findOrFail($id);
        if ($playlist->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $position = ($playlist->items()->max('position') ?? 0) + 1;

        $item = PlaylistItem::create([
            'playlist_id' => $playlist->id,
            'asset_id'    => $request->input('asset_id'),
            'position'    => $position,
        ]);

        return response()->json([
            'id'          => $item->id,
            'playlist_id' => $item->playlist_id,
            'asset_id'    => $item->asset_id,
            'position'    => $item->position,
        ], 201);
    }

    /**
     * DELETE /api/playlists/{id}/items/{itemId} - Remove an item from a playlist.
     */
    public function removeItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $playlist = Playlist::findOrFail($id);
        if ($playlist->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $item = PlaylistItem::where('id', $itemId)
            ->where('playlist_id', $playlist->id)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $item->delete();

        return response()->json(null, 204);
    }

    /**
     * PUT /api/playlists/{id}/items/order - Reorder items in a playlist.
     */
    public function reorderItems(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'items'             => ['required', 'array'],
            'items.*.id'        => ['required', 'integer'],
            'items.*.position'  => ['required', 'integer'],
        ]);

        $playlist = Playlist::findOrFail($id);
        if ($playlist->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        foreach ($request->input('items') as $entry) {
            PlaylistItem::where('id', $entry['id'])
                ->where('playlist_id', $playlist->id)
                ->update(['position' => $entry['position']]);
        }

        $updated = PlaylistItem::where('playlist_id', $playlist->id)
            ->orderBy('position')
            ->get()
            ->map(fn ($item) => [
                'id'          => $item->id,
                'playlist_id' => $item->playlist_id,
                'asset_id'    => $item->asset_id,
                'position'    => $item->position,
            ]);

        return response()->json($updated);
    }

    /**
     * DELETE /api/playlists/shares/{id} - Revoke a share.
     */
    public function revokeShare(Request $request, string $id): JsonResponse
    {
        $share = PlaylistShare::whereHas('playlist', function ($q) use ($request) {
            $q->where('owner_id', $request->user()->id);
        })->findOrFail($id);

        $share->revoked_at = now();
        $share->save();

        return response()->json(['message' => 'Share revoked.']);
    }

    /**
     * Generate a random 8-character share code from the allowed alphabet.
     */
    private function generateShareCode(): string
    {
        $alphabet = self::SHARE_CODE_ALPHABET;
        $length   = strlen($alphabet);
        $code     = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, $length - 1)];
        }

        // Ensure uniqueness
        while (PlaylistShare::where('code', $code)->exists()) {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, $length - 1)];
            }
        }

        return $code;
    }
}
