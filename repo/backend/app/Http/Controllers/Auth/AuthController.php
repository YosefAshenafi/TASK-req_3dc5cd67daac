<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * Attempt to log in a user.
     *
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $username = $request->input('username');
        $throttleKey = 'login_attempts:' . strtolower($username);

        // Check rate limit: max 5 attempts in 15 minutes
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message'          => 'Too many login attempts.',
                'retry_after'      => $seconds,
            ], 429);
        }

        $user = User::withTrashed()->where('username', $username)->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 15 * 60);
            $attemptsRemaining = max(0, 5 - RateLimiter::attempts($throttleKey));

            return response()->json([
                'message'           => 'Invalid credentials.',
                'attempts_remaining' => $attemptsRemaining,
            ], 401);
        }

        // Check if account is soft-deleted
        if ($user->trashed()) {
            RateLimiter::hit($throttleKey, 15 * 60);

            return response()->json([
                'message' => 'Invalid credentials.',
                'attempts_remaining' => 0,
            ], 401);
        }

        // Check if account is blacklisted
        if ($user->blacklisted_at !== null) {
            return response()->json([
                'message' => 'This account has been disabled.',
            ], 401);
        }

        // Check if account is frozen
        if ($user->frozen_until !== null && $user->frozen_until->isFuture()) {
            return response()->json([
                'message'      => 'Account is temporarily frozen.',
                'frozen_until' => $user->frozen_until->toIso8601String(),
            ], 423);
        }

        // Clear rate limiter on success
        RateLimiter::clear($throttleKey);

        // Create Sanctum token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user'       => [
                'id'       => $user->id,
                'username' => $user->username,
                'role'     => $user->role,
            ],
            'token'      => $token,
            'csrf_token' => csrf_token(),
        ]);
    }

    /**
     * Log out the current user.
     *
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current access token
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * Return the current authenticated user.
     *
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'              => $user->id,
            'username'        => $user->username,
            'role'            => $user->role,
            'frozen_until'    => $user->frozen_until?->toIso8601String(),
            'blacklisted_at'  => $user->blacklisted_at?->toIso8601String(),
            'favorites_count' => $user->favorites()->count(),
            'created_at'      => $user->created_at?->toIso8601String(),
        ]);
    }
}
