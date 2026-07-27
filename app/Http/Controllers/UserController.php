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
                'educator' => (int) ($roleCounts['educator'] ?? 0),
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

    public function directory(Request $request): JsonResponse
    {
        $query = User::query()->where('role', 'student');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        $students = $query->with('avatar')->orderBy('name')->get();

        $generations = $students
            ->groupBy(fn (User $student) => $student->generation ?: null)
            ->map(function ($studentsInGeneration, $generationKey) {
                $classes = $studentsInGeneration
                    ->groupBy(fn (User $student) => $student->class_name ?: null)
                    ->map(function ($studentsInClass, $classKey) {
                        return [
                            'class_name' => $classKey ?: null,
                            'student_count' => $studentsInClass->count(),
                            'students' => UserResource::collection($studentsInClass->values())->resolve(),
                        ];
                    })
                    ->values();

                $namedClasses = $classes->filter(fn ($c) => $c['class_name'] !== null)
                    ->sortBy('class_name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();
                $unassignedClasses = $classes->filter(fn ($c) => $c['class_name'] === null)->values();

                return [
                    'generation' => $generationKey ?: null,
                    'student_count' => $studentsInGeneration->count(),
                    'classes' => $namedClasses->concat($unassignedClasses)->values(),
                ];
            })
            ->values();

        $namedGenerations = $generations->filter(fn ($g) => $g['generation'] !== null)
            ->sortByDesc('generation', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $unassignedGenerations = $generations->filter(fn ($g) => $g['generation'] === null)->values();

        return response()->json([
            'generations' => $namedGenerations->concat($unassignedGenerations)->values(),
            'total_students' => $students->count(),
        ]);
    }

    public function assignedStudents(Request $request): JsonResponse
    {
        $educator = $request->user();

        $students = User::query()
            ->where('role', 'student')
            ->where('educator_id', $educator->id)
            ->with('avatar')
            ->get();

        return response()->json([
            'students' => UserResource::collection($students),
            'count' => $students->count(),
        ]);
    }
}
