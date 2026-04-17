<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateThumbnails;
use App\Jobs\IndexAsset;
use App\Models\Asset;
use App\Services\MediaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function __construct(
        private readonly MediaValidator $mediaValidator
    ) {}

    /**
     * GET /api/assets - List all ready assets with cursor pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('limit', 25), 100);
        $cursor  = $request->input('cursor');
        $sort    = $request->input('sort', 'newest');

        $query = Asset::where('status', 'ready');

        if ($cursor) {
            $query->where('id', '<', (int) $cursor);
        }

        switch ($sort) {
            case 'most_played':
                $query->orderByDesc('id'); // simplified; full sort via play_history join omitted for listing
                break;
            default:
                $query->orderByDesc('created_at')->orderByDesc('id');
                break;
        }

        $assets = $query->limit($perPage + 1)->get();

        $hasMore    = $assets->count() > $perPage;
        $assets     = $assets->take($perPage);
        $nextCursor = $hasMore ? (string) $assets->last()?->id : null;

        return response()->json([
            'items'       => $assets->map(fn ($a) => [
                'id'                => $a->id,
                'title'             => $a->title,
                'description'       => $a->description,
                'mime'              => $a->mime,
                'duration_seconds'  => $a->duration_seconds,
                'size_bytes'        => $a->size_bytes,
                'status'            => $a->status,
                'tags'              => $a->assetTags->pluck('tag'),
                'created_at'        => $a->created_at?->toIso8601String(),
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

        $uploadedFile  = $request->file('file');
        $clientMime    = $uploadedFile->getClientMimeType();
        $sniffedMime   = $uploadedFile->getMimeType() ?? $clientMime;
        $tempPath      = $uploadedFile->getRealPath();

        // Validate against client-declared MIME (detects disguised executables)
        $validationResult = $this->mediaValidator->validate($tempPath, $clientMime);

        if ($validationResult['valid'] && $sniffedMime !== $clientMime) {
            // Also check magic bytes don't contradict the sniffed type
            $sniffResult = $this->mediaValidator->validate($tempPath, $sniffedMime);
            if (! $sniffResult['valid']) {
                $validationResult = ['valid' => false, 'reason_code' => 'magic_mismatch', 'reason' => 'File content does not match declared type.', 'sha256' => null];
            }
        }

        $declaredMime = $sniffedMime;

        if (! $validationResult['valid']) {
            return response()->json([
                'message'     => $validationResult['reason'],
                'reason_code' => $validationResult['reason_code'],
            ], 422);
        }

        // Store the file
        $storedPath = $uploadedFile->store('media', 'local');

        // Create asset record
        $asset = Asset::create([
            'title'             => $request->input('title'),
            'description'       => $request->input('description'),
            'mime'              => $declaredMime,
            'duration_seconds'  => null,
            'size_bytes'        => $uploadedFile->getSize(),
            'file_path'         => $storedPath,
            'fingerprint_sha256' => $validationResult['sha256'],
            'status'            => 'processing',
            'uploaded_by'       => $request->user()->id,
        ]);

        // Attach tags if provided
        if ($request->filled('tags')) {
            foreach ($request->input('tags') as $tag) {
                $asset->assetTags()->create(['tag' => $tag]);
            }
        }

        // Dispatch background jobs
        GenerateThumbnails::dispatch($asset->id);
        IndexAsset::dispatch($asset->id);

        return response()->json([
            'id'     => $asset->id,
            'title'  => $asset->title,
            'status' => $asset->status,
            'mime'   => $asset->mime,
        ], 201);
    }

    /**
     * GET /api/assets/{id} - Get an asset's details.
     */
    public function show(string $id): JsonResponse
    {
        $asset = Asset::with(['uploader', 'assetTags'])->findOrFail($id);

        return response()->json([
            'id'                 => $asset->id,
            'title'              => $asset->title,
            'description'        => $asset->description,
            'mime'               => $asset->mime,
            'duration_seconds'   => $asset->duration_seconds,
            'size_bytes'         => $asset->size_bytes,
            'file_path'          => $asset->file_path,
            'fingerprint_sha256' => $asset->fingerprint_sha256,
            'status'             => $asset->status,
            'uploaded_by'        => $asset->uploaded_by,
            'uploader'           => $asset->uploader ? [
                'id'       => $asset->uploader->id,
                'username' => $asset->uploader->username,
            ] : null,
            'tags'       => $asset->assetTags->pluck('tag'),
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * DELETE /api/assets/{id} - Soft delete an asset (admin only).
     * Returns 409 if asset is referenced in any playlist.
     */
    public function destroy(string $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        // Check if asset is referenced in any playlist
        $referenceCount = $asset->playlistItems()->count();
        if ($referenceCount > 0) {
            return response()->json([
                'message'          => 'Asset is referenced in one or more playlists and cannot be deleted.',
                'reference_count'  => $referenceCount,
            ], 409);
        }

        $asset->delete();

        return response()->json(null, 204);
    }
}
