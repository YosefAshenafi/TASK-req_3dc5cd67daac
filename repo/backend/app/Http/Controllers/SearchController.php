<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\FeatureFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * GET /api/search
     *
     * Query params:
     *   q           - text search query
     *   tags[]      - filter by tags
     *   duration_lt - max duration_seconds
     *   recent_days - only assets from last N days
     *   sort        - most_played | newest | recommended
     *   cursor      - pagination cursor (asset id)
     *   per_page    - results per page (default 25, max 100)
     */
    public function index(Request $request): JsonResponse
    {
        $startMs      = (int) round(microtime(true) * 1000);
        $sort         = $request->input('sort', 'newest');
        $originalSort = $sort;
        $perPage      = min((int) $request->input('per_page', 25), 100);

        $degraded = false;

        // Check if recommended sort is allowed
        if ($sort === 'recommended') {
            $flag = FeatureFlag::find('recommended_enabled');
            if (! $flag || ! $flag->enabled) {
                $sort     = 'most_played';
                $degraded = true;
            }
        }

        $query = Asset::query()
            ->where('assets.status', 'ready');

        // Full-text search via search_index join
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');
            $query->join('search_index', 'assets.id', '=', 'search_index.asset_id')
                  ->where(function ($q) use ($searchTerm) {
                      $q->where('search_index.tokenized_title', 'like', '%' . $searchTerm . '%')
                        ->orWhere('search_index.tokenized_body', 'like', '%' . $searchTerm . '%');
                  })
                  ->addSelect('assets.*');
        } else {
            $query->select('assets.*');
        }

        // Tag filter
        if ($request->filled('tags')) {
            $tags = (array) $request->input('tags');
            foreach ($tags as $tag) {
                $query->whereExists(function ($sub) use ($tag) {
                    $sub->select(DB::raw(1))
                        ->from('asset_tags')
                        ->whereColumn('asset_tags.asset_id', 'assets.id')
                        ->where('asset_tags.tag', $tag);
                });
            }
        }

        // Duration filter
        if ($request->filled('duration_lt')) {
            $query->where('assets.duration_seconds', '<', (int) $request->input('duration_lt'));
        }

        // Recent days filter
        if ($request->filled('recent_days')) {
            $query->where('assets.created_at', '>=', now()->subDays((int) $request->input('recent_days')));
        }

        // Cursor pagination
        if ($request->filled('cursor')) {
            $query->where('assets.id', '>', (int) $request->input('cursor'));
        }

        // Apply sort
        switch ($sort) {
            case 'most_played':
                $query->leftJoin(
                    DB::raw('(SELECT asset_id, COUNT(*) as play_count FROM play_history GROUP BY asset_id) as ph_counts'),
                    'assets.id',
                    '=',
                    'ph_counts.asset_id'
                )
                ->orderByDesc(DB::raw('COALESCE(ph_counts.play_count, 0)'))
                ->orderByDesc('assets.id');
                break;

            case 'recommended':
                $userId = $request->user()->id;
                $query->leftJoin('recommendation_candidates', function ($join) use ($userId) {
                    $join->on('assets.id', '=', 'recommendation_candidates.asset_id')
                         ->where('recommendation_candidates.user_id', '=', $userId);
                })
                ->orderByDesc(DB::raw('COALESCE(recommendation_candidates.score, 0)'))
                ->orderByDesc('assets.id');
                $query->addSelect(DB::raw('recommendation_candidates.reason_tags_json'));
                break;

            case 'newest':
            default:
                $query->orderByDesc('assets.created_at')->orderByDesc('assets.id');
                break;
        }

        $items = $query->limit($perPage + 1)->get();

        $hasMore    = $items->count() > $perPage;
        $items      = $items->take($perPage);
        $nextCursor = $hasMore ? $items->last()?->id : null;

        // Track recommendation hit rate
        if ($originalSort === 'recommended') {
            Cache::increment('monitoring:recommendation_requests');
            $hits = $items->filter(fn ($a) => ! is_null(json_decode($a->reason_tags_json ?? 'null')))->count();
            if ($hits > 0) {
                Cache::increment('monitoring:recommendation_hits');
            }
        }

        $response = response()->json([
            'items'       => $items->map(fn ($a) => [
                'id'                => $a->id,
                'title'             => $a->title,
                'mime'              => $a->mime,
                'duration_seconds'  => $a->duration_seconds,
                'status'            => $a->status,
                'created_at'        => $a->created_at?->toIso8601String(),
                'reason_tags'       => $sort === 'recommended'
                    ? (json_decode($a->reason_tags_json ?? '[]', true) ?? [])
                    : null,
            ]),
            'next_cursor' => $nextCursor,
            'degraded'    => $degraded,
        ]);

        if ($degraded) {
            $response->header('X-Recommendation-Degraded', 'true');
        }

        // Record latency to Redis rolling window
        $latencyMs     = (int) round(microtime(true) * 1000) - $startMs;
        $windowSeconds = config('smartpark.latency_window_minutes', 5) * 60;
        $now           = time();
        try {
            Cache::getRedis()->zAdd('monitoring:latency_samples', $now, "{$now}:{$latencyMs}");
            Cache::getRedis()->zRemRangeByScore('monitoring:latency_samples', '-inf', $now - $windowSeconds);
        } catch (\Throwable) {
            // Redis unavailable — don't break the search response
        }

        return $response;
    }
}
