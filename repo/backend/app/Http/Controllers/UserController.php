<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /api/users - Paginated list of users (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('query')) {
            $query->where('username', 'like', '%' . $request->input('query') . '%');
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'frozen') {
                $query->where('frozen_until', '>', now());
            } elseif ($status === 'blacklisted') {
                $query->whereNotNull('blacklisted_at');
            } elseif ($status === 'active') {
                $query->whereNull('blacklisted_at')
                      ->where(function ($q) {
                          $q->whereNull('frozen_until')->orWhere('frozen_until', '<=', now());
                      });
            }
        }

        $users = $query->get();

        return response()->json([
            'items'       => $users->map(fn ($u) => [
                'id'            => $u->id,
                'username'      => $u->username,
                'role'          => $u->role,
                'frozen_until'  => $u->frozen_until?->toIso8601String(),
                'blacklisted_at' => $u->blacklisted_at?->toIso8601String(),
                'deleted_at'    => $u->deleted_at?->toIso8601String(),
                'created_at'    => $u->created_at?->toIso8601String(),
            ]),
            'next_cursor' => null,
        ]);
    }

    /**
     * POST /api/users - Create a new user (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', Rule::in(['user', 'admin', 'technician'])],
            'email'    => ['nullable', 'email'],
        ]);

        $user = User::create([
            'username'  => $validated['username'],
            'password'  => Hash::make($validated['password']),
            'role'      => $validated['role'],
            'email_enc' => $validated['email'] ?? null,
        ]);

        return response()->json([
            'id'       => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
        ], 201);
    }

    /**
     * GET /api/users/{id} - Get a user by ID.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'id'             => $user->id,
            'username'       => $user->username,
            'role'           => $user->role,
            'frozen_until'   => $user->frozen_until?->toIso8601String(),
            'blacklisted_at' => $user->blacklisted_at?->toIso8601String(),
            'created_at'     => $user->created_at?->toIso8601String(),
        ]);
    }

    /**
     * PUT/PATCH /api/users/{id} - Update a user.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'role'     => ['sometimes', Rule::in(['user', 'admin', 'technician'])],
            'password' => ['sometimes', 'string', 'min:8'],
            'email'    => ['sometimes', 'nullable', 'email'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        if (array_key_exists('email', $validated)) {
            $user->email_enc = $validated['email'];
            unset($validated['email']);
        }

        $user->fill($validated)->save();

        return response()->json([
            'id'       => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
        ]);
    }

    /**
     * PATCH /api/users/{id}/freeze - Freeze a user account for the specified duration.
     */
    public function freeze(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'duration_hours' => ['required', 'integer', 'min:1', 'max:8760'],
        ]);

        $user = User::findOrFail($id);
        $user->frozen_until = now()->addHours($request->input('duration_hours'));
        $user->save();

        return response()->json($this->userResource($user));
    }

    /**
     * PATCH /api/users/{id}/unfreeze - Unfreeze a user account.
     */
    public function unfreeze(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->frozen_until = null;
        $user->save();

        return response()->json($this->userResource($user));
    }

    /**
     * PATCH /api/users/{id}/blacklist - Blacklist a user account.
     */
    public function blacklist(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->blacklisted_at = now();
        $user->save();

        // Revoke all tokens immediately
        $user->tokens()->delete();

        return response()->json($this->userResource($user));
    }

    private function userResource(User $user): array
    {
        return [
            'id'             => $user->id,
            'username'       => $user->username,
            'role'           => $user->role,
            'frozen_until'   => $user->frozen_until?->toIso8601String(),
            'blacklisted_at' => $user->blacklisted_at?->toIso8601String(),
            'deleted_at'     => $user->deleted_at?->toIso8601String(),
            'created_at'     => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * DELETE /api/users/{id} - Soft delete a user.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(null, 204);
    }
}
