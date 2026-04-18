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
     *
     * Only assets with status='ready' are playable by non-admin users. Recording a play
     * against a non-ready asset would surface that asset's id on the user's own history
     * feed, leaking the existence of an unpublished/failed upload.
     */
    public function play(Request $request, string $id): JsonResponse
    {
        $asset   = Asset::findOrFail($id);
        $isAdmin = $request->user()?->role === 'admin';

        if (! $isAdmin && $asset->status !== 'ready') {
            return response()->json(['message' => 'Asset not found.'], 404);
        }

        $request->validate([
            'session_id' => ['nullable', 'string', 'max:255'],
            'context'    => ['nullable', 'string', 'max:255'],
        ]);

        $entry = PlayHistory::create([
            'user_id'    => $request->user()->id,
            'asset_id'   => $id,
            'played_at'  => now(),
            'session_id' => $request->input('session_id'),
            'context'    => $request->input('context'),
        ]);

        return response()->json([
            'id'         => $entry->id,
            'user_id'    => $entry->user_id,
            'asset_id'   => $entry->asset_id,
            'played_at'  => $entry->played_at?->toIso8601String(),
            'session_id' => $entry->session_id,
            'context'    => $entry->context,
        ], 202);
    }

    /**
     * GET /api/history - Get the authenticated user's play history.
     *
     * Query params:
     *   cursor   - opaque id cursor; page returns rows with id strictly less than this
     *   per_page - results per page (default 25, max 100)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 100);
        $cursor  = $request->input('cursor');

        $query = PlayHistory::with('asset')
            ->where('user_id', $request->user()->id);

        // Cursor pagination: we order by played_at desc with id desc as tiebreaker.
        // Because id is monotonically increasing per user, `id < cursor` advances pages
        // without skipping rows with duplicate played_at timestamps.
        if ($cursor) {
            $query->where('id', '<', (int) $cursor);
        }

        $history = $query
            ->orderByDesc('played_at')
            ->orderByDesc('id')
            ->limit($perPage + 1)
            ->get();

        $hasMore    = $history->count() > $perPage;
        $history    = $history->take($perPage);
        $nextCursor = $hasMore ? (string) $history->last()?->id : null;

        $isAdmin = $request->user()?->role === 'admin';

        return response()->json([
            'items' => $history->map(function ($h) use ($isAdmin) {
                if (! $h->asset) {
                    return [
                        'id'         => $h->id,
                        'asset_id'   => $h->asset_id,
                        'played_at'  => $h->played_at?->toIso8601String(),
                        'session_id' => $h->session_id,
                        'context'    => $h->context,
                        'asset'      => null,
                    ];
                }

                $isReady    = $h->asset->status === 'ready';
                $exposeMeta = $isAdmin || $isReady;

                return [
                    'id'         => $h->id,
                    'asset_id'   => $h->asset_id,
                    'played_at'  => $h->played_at?->toIso8601String(),
                    'session_id' => $h->session_id,
                    'context'    => $h->context,
                    'asset'      => [
                        'id'    => $h->asset->id,
                        'title' => $exposeMeta ? $h->asset->title : null,
                        'mime'  => $exposeMeta ? $h->asset->mime  : null,
                    ],
                ];
            }),
            'next_cursor' => $nextCursor,
        ]);
    }

    /**
     * GET /api/history/sessions - Group the caller's play history by session_id.
     *
     * Each session represents one continuous listening context (the client passes the same
     * session_id for every `/assets/{id}/play` call within the session). Null session_ids
     * are grouped into a single "unassigned" bucket so legacy/unlabelled plays are still
     * surfaced to the user.
     *
     * Query params:
     *   limit - max sessions returned (default 20, max 100)
     */
    public function sessions(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 20), 100);

        $history = PlayHistory::with('asset')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('played_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $isAdmin = $request->user()?->role === 'admin';

        $sessions = [];
        foreach ($history as $h) {
            $key = $h->session_id ?? '__unassigned__';
            if (! isset($sessions[$key])) {
                $sessions[$key] = [
                    'session_id'  => $h->session_id,
                    'started_at'  => $h->played_at?->toIso8601String(),
                    'ended_at'    => $h->played_at?->toIso8601String(),
                    'play_count'  => 0,
                    'context'     => $h->context,
                    'items'       => [],
                ];
            }

            $sessions[$key]['play_count']++;
            // played_at is already ordered desc — the first row we see is the newest ("ended_at")
            // and the last one we see is the oldest ("started_at").
            $sessions[$key]['started_at'] = $h->played_at?->toIso8601String();

            $isReady    = $h->asset?->status === 'ready';
            $exposeMeta = $isAdmin || $isReady;

            $sessions[$key]['items'][] = [
                'id'        => $h->id,
                'asset_id'  => $h->asset_id,
                'played_at' => $h->played_at?->toIso8601String(),
                'context'   => $h->context,
                'asset'     => $h->asset ? [
                    'id'    => $h->asset->id,
                    'title' => $exposeMeta ? $h->asset->title : null,
                    'mime'  => $exposeMeta ? $h->asset->mime  : null,
                ] : null,
            ];
        }

        // Sort sessions by most recent activity and cap the response to `limit`.
        $out = array_values($sessions);
        usort($out, fn ($a, $b) => strcmp($b['ended_at'] ?? '', $a['ended_at'] ?? ''));
        $out = array_slice($out, 0, $limit);

        return response()->json([
            'sessions' => $out,
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
                'id'         => $current->id,
                'asset_id'   => $current->asset_id,
                'played_at'  => $current->played_at?->toIso8601String(),
                'session_id' => $current->session_id,
                'context'    => $current->context,
                'asset'      => $current->asset ? [
                    'id'    => $current->asset->id,
                    'title' => $current->asset->title,
                    'mime'  => $current->asset->mime,
                ] : null,
            ] : null,
            'recent' => $recentItems->map(fn ($h) => [
                'id'         => $h->id,
                'asset_id'   => $h->asset_id,
                'played_at'  => $h->played_at?->toIso8601String(),
                'session_id' => $h->session_id,
                'context'    => $h->context,
                'asset'      => $h->asset ? [
                    'id'    => $h->asset->id,
                    'title' => $h->asset->title,
                    'mime'  => $h->asset->mime,
                ] : null,
            ]),
            'current_session_id' => $current?->session_id,
        ]);
    }
}
