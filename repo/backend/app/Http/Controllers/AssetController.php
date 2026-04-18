<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateThumbnails;
use App\Jobs\IndexAsset;
use App\Jobs\MediaScanRequested;
use App\Models\Asset;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\PlaylistItem;
use App\Models\RecommendationCandidate;
use App\Models\SearchIndex;
use App\Services\MediaProbe;
use App\Services\MediaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function __construct(
        private readonly MediaValidator $mediaValidator,
        private readonly MediaProbe $mediaProbe,
    ) {}

    /**
     * GET /api/assets - List assets with cursor pagination.
     *
     * Non-admins only ever see `status='ready'` so the admin review queue of
     * processing/failed uploads stays invisible to regular users.
     *
     * Admins additionally accept a `status` filter (`ready`, `processing`, `failed`, or
     * `all`) so the upload-review console can fetch the queue of pending/failed assets
     * without a separate endpoint. Default for admins remains `ready` to avoid surprising
     * callers that have been reading the current default.
     *
     * Query params:
     *   sort   - newest (default) | most_played
     *   cursor - paginate by asset id (descending)
     *   limit  - results per page (default 25, max 100)
     *   status - (admin only) ready | processing | failed | all
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('limit', 25), 100);
        $cursor  = $request->input('cursor');
        $sort    = $request->input('sort', 'newest');
        $isAdmin = $request->user()?->role === 'admin';

        $query = Asset::query()
            ->from('assets')
            ->select('assets.*');

        if ($isAdmin) {
            $statusFilter = $request->input('status', 'ready');
            $allowed      = ['ready', 'processing', 'failed', 'all'];
            if (! in_array($statusFilter, $allowed, true)) {
                return response()->json([
                    'message'     => "Invalid status filter '{$statusFilter}'. Allowed: " . implode(', ', $allowed),
                    'reason_code' => 'invalid_status_filter',
                ], 422);
            }
            if ($statusFilter !== 'all') {
                $query->where('assets.status', $statusFilter);
            }
        } else {
            $query->where('assets.status', 'ready');
        }

        if ($cursor) {
            $query->where('assets.id', '<', (int) $cursor);
        }

        switch ($sort) {
            case 'most_played':
                $query->leftJoin(
                    DB::raw('(SELECT asset_id, COUNT(*) as play_count FROM play_history GROUP BY asset_id) as ph_counts'),
                    'assets.id',
                    '=',
                    'ph_counts.asset_id'
                )
                ->addSelect(DB::raw('COALESCE(ph_counts.play_count, 0) as play_count'))
                ->orderByDesc(DB::raw('COALESCE(ph_counts.play_count, 0)'))
                ->orderByDesc('assets.id');
                break;
            case 'newest':
            default:
                $query->orderByDesc('assets.created_at')->orderByDesc('assets.id');
                break;
        }

        $assets = $query->limit($perPage + 1)->get();

        $hasMore    = $assets->count() > $perPage;
        $assets     = $assets->take($perPage);
        $nextCursor = $hasMore ? (string) $assets->last()?->id : null;

        return response()->json([
            'items'       => $assets->map(fn ($a) => [
                'id'               => $a->id,
                'title'            => $a->title,
                'description'      => $a->description,
                'mime'             => $a->mime,
                'duration_seconds' => $a->duration_seconds,
                'size_bytes'       => $a->size_bytes,
                'status'           => $a->status,
                'thumbnail_urls'   => $this->buildThumbnailUrls($a),
                'tags'             => $a->assetTags->pluck('tag'),
                'played_count'     => isset($a->play_count) ? (int) $a->play_count : null,
                'created_at'       => $a->created_at?->toIso8601String(),
            ]),
            'next_cursor' => $nextCursor,
        ]);
    }

    /**
     * POST /api/assets - Upload a new asset (admin only, multipart).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'        => ['required', 'file'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tags'        => ['nullable', 'array'],
            'tags.*'      => ['string', 'max:50'],
        ]);

        $uploadedFile = $request->file('file');
        $clientMime   = $uploadedFile->getClientMimeType();
        $tempPath     = $uploadedFile->getRealPath();

        $validationResult = $this->mediaValidator->validate($tempPath, $clientMime);

        if (! $validationResult['valid']) {
            return response()->json([
                'message'     => $validationResult['reason'],
                'reason_code' => $validationResult['reason_code'],
            ], 422);
        }

        // Persist the server-sniffed MIME, never the client-declared one.
        $sniffedMime = $validationResult['sniffed_mime'];

        $durationSeconds = $this->mediaProbe->getDurationSeconds($tempPath, $sniffedMime);

        $storedPath = $uploadedFile->store('media', 'local');

        $asset = Asset::create([
            'title'              => $request->input('title'),
            'description'        => $request->input('description'),
            'mime'               => $sniffedMime,
            'duration_seconds'   => $durationSeconds,
            'size_bytes'         => $uploadedFile->getSize(),
            'file_path'          => $storedPath,
            'fingerprint_sha256' => $validationResult['sha256'],
            'status'             => 'processing',
            'uploaded_by'        => $request->user()->id,
        ]);

        if ($request->filled('tags')) {
            foreach ($request->input('tags') as $tag) {
                $asset->assetTags()->create(['tag' => $tag]);
            }
        }

        GenerateThumbnails::dispatch($asset->id);
        IndexAsset::dispatch($asset->id);
        MediaScanRequested::dispatch($asset->id);

        return response()->json([
            'id'               => $asset->id,
            'title'            => $asset->title,
            'status'           => $asset->status,
            'mime'             => $asset->mime,
            'duration_seconds' => $asset->duration_seconds,
        ], 201);
    }

    /**
     * GET /api/assets/{id} - Get an asset's details.
     *
     * Non-admins may only read assets with status='ready'. Pending/processing/failed
     * assets are hidden from end users because admins are the approval gate.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $asset   = Asset::with(['uploader', 'assetTags'])->findOrFail($id);
        $isAdmin = $request->user()?->role === 'admin';

        if (! $isAdmin && $asset->status !== 'ready') {
            // Return 404 (not 403) so we don't leak the existence of unpublished assets.
            return response()->json(['message' => 'Asset not found.'], 404);
        }

        $data = [
            'id'               => $asset->id,
            'title'            => $asset->title,
            'description'      => $asset->description,
            'mime'             => $asset->mime,
            'duration_seconds' => $asset->duration_seconds,
            'size_bytes'       => $asset->size_bytes,
            'status'           => $asset->status,
            'thumbnail_urls'   => $this->buildThumbnailUrls($asset),
            'uploaded_by'      => $asset->uploaded_by,
            'uploader'         => $asset->uploader ? [
                'id'       => $asset->uploader->id,
                'username' => $asset->uploader->username,
            ] : null,
            'tags'       => $asset->assetTags->pluck('tag'),
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];

        // Only expose internal paths and fingerprints to admins (S1)
        if ($isAdmin) {
            $data['file_path']          = $asset->file_path;
            $data['fingerprint_sha256'] = $asset->fingerprint_sha256;
        }

        return response()->json($data);
    }

    /**
     * DELETE /api/assets/{id} - Soft delete an asset (admin only).
     * Returns 409 if asset is referenced in any playlist.
     */
    public function destroy(string $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        $referenceCount = $asset->playlistItems()->count();
        if ($referenceCount > 0) {
            return response()->json([
                'message'         => 'Asset is referenced in one or more playlists and cannot be deleted.',
                'reference_count' => $referenceCount,
            ], 409);
        }

        $asset->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/admin/assets/{id}/replace - Replace an asset (admin only).
     * Atomically remaps all references from the old asset to the new one.
     */
    public function replace(Request $request, string $id): JsonResponse
    {
        $oldAsset = Asset::findOrFail($id);

        $request->validate([
            'file'        => ['required', 'file'],
            'title'       => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tags'        => ['nullable', 'array'],
            'tags.*'      => ['string', 'max:50'],
        ]);

        $uploadedFile = $request->file('file');
        $clientMime   = $uploadedFile->getClientMimeType();
        $tempPath     = $uploadedFile->getRealPath();

        $validationResult = $this->mediaValidator->validate($tempPath, $clientMime);

        if (! $validationResult['valid']) {
            return response()->json([
                'message'     => $validationResult['reason'],
                'reason_code' => $validationResult['reason_code'],
            ], 422);
        }

        $sniffedMime     = $validationResult['sniffed_mime'];
        $durationSeconds = $this->mediaProbe->getDurationSeconds($tempPath, $sniffedMime);
        $storedPath      = $uploadedFile->store('media', 'local');

        $result = DB::transaction(function () use ($request, $oldAsset, $storedPath, $sniffedMime, $durationSeconds, $validationResult) {
            $newAsset = Asset::create([
                'title'              => $request->input('title', $oldAsset->title),
                'description'        => $request->input('description', $oldAsset->description),
                'mime'               => $sniffedMime,
                'duration_seconds'   => $durationSeconds,
                'size_bytes'         => $request->file('file')->getSize(),
                'file_path'          => $storedPath,
                'fingerprint_sha256' => $validationResult['sha256'],
                'status'             => 'processing',
                'uploaded_by'        => $request->user()->id,
            ]);

            if ($request->filled('tags')) {
                foreach ($request->input('tags') as $tag) {
                    $newAsset->assetTags()->create(['tag' => $tag]);
                }
            }

            $playlistCount       = PlaylistItem::where('asset_id', $oldAsset->id)->update(['asset_id' => $newAsset->id]);
            $favoritesCount      = Favorite::where('asset_id', $oldAsset->id)->update(['asset_id' => $newAsset->id]);
            $historyCount        = PlayHistory::where('asset_id', $oldAsset->id)->update(['asset_id' => $newAsset->id]);
            $recommendationCount = RecommendationCandidate::where('asset_id', $oldAsset->id)->update(['asset_id' => $newAsset->id]);
            SearchIndex::where('asset_id', $oldAsset->id)->update(['asset_id' => $newAsset->id]);

            $oldAsset->delete();

            GenerateThumbnails::dispatch($newAsset->id);
            IndexAsset::dispatch($newAsset->id);
            MediaScanRequested::dispatch($newAsset->id);

            return [
                'old_asset_id'        => $oldAsset->id,
                'new_asset_id'        => $newAsset->id,
                'remapped_playlists'  => $playlistCount,
                'remapped_favorites'  => $favoritesCount,
                'remapped_history'    => $historyCount,
                'remapped_candidates' => $recommendationCount,
            ];
        });

        return response()->json($result, 201);
    }

    private function buildThumbnailUrls(Asset $asset): ?array
    {
        if (empty($asset->thumbnail_urls)) {
            return null;
        }

        return array_map(
            fn ($path) => Storage::disk('public')->url($path),
            $asset->thumbnail_urls
        );
    }
}
