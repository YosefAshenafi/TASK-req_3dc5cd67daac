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
}
