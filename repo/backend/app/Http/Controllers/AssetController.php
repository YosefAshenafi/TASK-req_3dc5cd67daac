<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssetRequest;
use App\Http\Resources\AssetResource;
use App\Jobs\GenerateThumbnailsJob;
use App\Jobs\IndexAssetJob;
use App\Models\Asset;
use App\Services\DegradationService;
use App\Services\MimeValidationService;
use App\Services\ScanHookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function __construct(
        private readonly MimeValidationService $mimeValidator,
        private readonly ScanHookService $scanner,
        private readonly DegradationService $degradation,
    ) {}

    public function index(Request $request): ResourceCollection|JsonResponse
    {
        $query = Asset::query()->where('status', 'approved');

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $ids = DB::table('asset_search_index')
                ->whereRaw('MATCH(search_text) AGAINST(? IN BOOLEAN MODE)', [$term . '*'])
                ->pluck('asset_id');
            $query->whereIn('id', $ids);
        }

        if ($request->filled('tags')) {
            $tags = $request->input('tags');
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $query->whereJsonContains('tags', $tag);
                }
            }
        }

        if ($request->filled('duration_max')) {
            $query->where('duration', '<=', (int) $request->input('duration_max'));
        }

        if ($request->filled('recency_days')) {
            $days = (int) $request->input('recency_days');
            $query->where('created_at', '>=', now()->subDays($days));
        }

        $sort = $request->input('sort', 'newest');

        match ($sort) {
            'most_played' => $query->orderByDesc('play_count'),
            'recommended' => $this->applySortRecommended($query, $request->user()->id),
            default => $query->orderByDesc('created_at'),
        };

        $assets = $query->paginate(20);

        return AssetResource::collection($assets);
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $file = $request->file('file');

        $validationResult = $this->mimeValidator->validate($file);
        if (!$validationResult['valid']) {
            return response()->json([
                'message' => $validationResult['error'],
                'errors' => ['file' => [$validationResult['error']]],
            ], 422);
        }

        $scanResult = $this->scanner->scan($file->getPathname());
        if (!$scanResult['clean']) {
            return response()->json([
                'message' => 'File failed security scan.',
                'errors' => ['file' => ['File failed security scan.']],
            ], 409);
        }

        $relativePath = 'media/' . uniqid('', true) . '.' . $file->getClientOriginalExtension();
        Storage::disk('local')->put($relativePath, file_get_contents($file->getPathname()));

        $asset = Asset::create([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'tags' => $request->validated('tags') ?? [],
            'mime_type' => $validationResult['mime_type'],
            'file_path' => $relativePath,
            'file_size' => $file->getSize(),
            'duration' => $request->validated('duration'),
            'status' => 'pending',
            'uploaded_by' => $request->user()->id,
            'play_count' => 0,
        ]);

        GenerateThumbnailsJob::dispatch($asset->id);
        IndexAssetJob::dispatch($asset->id);

        Log::info('asset_uploaded', ['asset_id' => $asset->id, 'mime' => $asset->mime_type]);

        return response()->json(['data' => new AssetResource($asset)], 201);
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        $user = $request->user();
        if ($asset->status !== 'approved' && $asset->uploaded_by !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'Resource not found.', 'errors' => []], 404);
        }

        return response()->json(['data' => new AssetResource($asset)]);
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        if ($asset->uploaded_by !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.', 'errors' => []], 403);
        }

        if ($asset->isReferencedByPlaylist()) {
            return response()->json([
                'message' => 'Asset is referenced by one or more playlists and cannot be deleted.',
                'errors' => [],
            ], 409);
        }

        $asset->delete();

        return response()->json(null, 204);
    }

    private function applySortRecommended(mixed $query, int $userId): void
    {
        if ($this->degradation->isDisabled()) {
            $query->orderByDesc('play_count');
            return;
        }

        $query->leftJoin('recommendation_scores', function ($join) use ($userId): void {
            $join->on('assets.id', '=', 'recommendation_scores.asset_id')
                ->where('recommendation_scores.user_id', '=', $userId);
        })
        ->orderByRaw('COALESCE(recommendation_scores.score, 0) DESC')
        ->select('assets.*');
    }
}
