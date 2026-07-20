<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminCreateUserRequest;
use App\Http\Requests\AdminUpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Avatar;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 10;

        $users = $query
            ->with('avatar')
            ->latest()
            ->paginate($perPage);

        $roleCounts = User::selectRaw('role, count(*) as count')->groupBy('role')->pluck('count', 'role');

        return response()->json([
            'users' => UserResource::collection($users->items()),
            'count' => $users->total(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'counts' => [
                'total' => User::count(),
                'student' => (int) ($roleCounts['student'] ?? 0),
                'trainer' => (int) ($roleCounts['trainer'] ?? 0),
                'admin' => (int) ($roleCounts['admin'] ?? 0),
            ],
        ]);
    }

    public function store(AdminCreateUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = $data['role'] ?? 'student';

        $defaultPassword = config('auth.default_new_user_password', 'Student@123');
        $data['password'] = Hash::make($defaultPassword);

        $user = User::create($data);

        $defaultAvatar = Avatar::fallbackFor($data['gender'] ?? null);
        if ($defaultAvatar) {
            $user->avatar_id = $defaultAvatar->id;
            $user->save();
        }

        return response()->json([
            'message' => 'User created successfully.',
            'user' => new UserResource($user->fresh()->load('avatar')),
            // Returned once so the admin can share it with the new user.
            'default_password' => $defaultPassword,
        ], 201);
    }

    public function update(AdminUpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => new UserResource($user->fresh()->load('avatar')),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }
}
