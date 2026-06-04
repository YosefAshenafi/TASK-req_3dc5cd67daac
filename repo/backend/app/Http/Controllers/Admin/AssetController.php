<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Asset::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $assets = $query->orderByDesc('created_at')->paginate(50);

        return response()->json([
            'data' => AssetResource::collection($assets->items()),
            'meta' => [
                'total' => $assets->total(),
                'page' => $assets->currentPage(),
            ],
        ]);
    }

    public function approve(Request $request, Asset $asset): JsonResponse
    {
        $asset->update(['status' => 'approved']);

        Log::info('admin_approve_asset', [
            'admin_id' => $request->user()->id,
            'asset_id' => $asset->id,
        ]);

        return response()->json(['data' => new AssetResource($asset->fresh())]);
    }

    public function reject(Request $request, Asset $asset): JsonResponse
    {
        $asset->update(['status' => 'rejected']);

        Log::info('admin_reject_asset', [
            'admin_id' => $request->user()->id,
            'asset_id' => $asset->id,
        ]);

        return response()->json(['data' => new AssetResource($asset->fresh())]);
    }

    /**
     * Update editable metadata (title, description, tags) of any asset.
     * Administrators only — enforced by the `role:admin` route middleware.
     * Uses `sometimes` so callers may send a partial payload; only the
     * supplied fields are validated and persisted.
     */
    public function update(Request $request, Asset $asset): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        // Normalise an explicit null tags payload to an empty list so the
        // JSON column stays a consistent array shape.
        if (array_key_exists('tags', $data) && $data['tags'] === null) {
            $data['tags'] = [];
        }

        $asset->update($data);

        Log::info('admin_update_asset', [
            'admin_id' => $request->user()->id,
            'asset_id' => $asset->id,
            'fields' => array_keys($data),
        ]);

        return response()->json(['data' => new AssetResource($asset->fresh())]);
    }
}
