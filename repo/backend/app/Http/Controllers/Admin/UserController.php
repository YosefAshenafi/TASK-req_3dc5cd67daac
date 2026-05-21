<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::withTrashed()
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'total' => $users->total(),
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
            ],
        ]);
    }

    public function freeze(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'duration_hours' => ['required', 'integer', 'min:1', 'max:8760'],
        ]);

        $user->update([
            'account_status' => 'frozen',
            'frozen_until' => now()->addHours($data['duration_hours']),
        ]);

        Log::warning('admin_freeze_user', [
            'admin_id' => $request->user()->id,
            'target_user_id' => $user->id,
            'duration_hours' => $data['duration_hours'],
        ]);

        return response()->json(['data' => new UserResource($user->fresh())]);
    }

    public function blacklist(Request $request, User $user): JsonResponse
    {
        $user->update([
            'account_status' => 'blacklisted',
            'frozen_until' => null,
        ]);

        Log::warning('admin_blacklist_user', [
            'admin_id' => $request->user()->id,
            'target_user_id' => $user->id,
        ]);

        return response()->json(['data' => new UserResource($user->fresh())]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $user->delete();

        Log::warning('admin_delete_user', [
            'admin_id' => $request->user()->id,
            'target_user_id' => $user->id,
        ]);

        return response()->json(null, 204);
    }
}
