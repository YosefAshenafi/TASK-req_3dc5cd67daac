<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('username', $request->validated('username'))
            ->whereNull('deleted_at')
            ->first();

        if ($user === null || !Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
                'errors' => [],
            ], 401);
        }

        if ($user->isBlacklisted()) {
            return response()->json([
                'message' => 'Account has been permanently suspended.',
                'errors' => [],
            ], 401);
        }

        if ($user->isFrozen()) {
            return response()->json([
                'message' => 'Account is temporarily frozen.',
                'errors' => [],
            ], 401);
        }

        $request->session()->regenerate();
        auth('web')->login($user);

        Log::info('auth_login', ['user_id' => $user->id, 'role' => $user->role]);

        return response()->json([
            'message' => 'Logged in.',
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Log::info('auth_logout', ['user_id' => $request->user()->id]);

        auth('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }
}
