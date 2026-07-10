<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Avatar;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['role'] = $data['role'] ?? 'student';

        $user = User::create($data);

        $defaultAvatar = Avatar::where('is_default', true)->inRandomOrder()->first();

        if ($defaultAvatar) {
            $user->avatar_id = $defaultAvatar->id;
            $user->save();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password reset successfully']);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(),
        ], 200);
    }

    public function getDefaultAvatars()
    {
        $defaultAvatars = Avatar::where('is_default', true)
            ->get()
            ->map(fn ($avatar) => [
                'id' => $avatar->id,
                'filename' => $avatar->filename,
                'url' => asset($avatar->path),
            ]);

        return response()->json([
            'avatars' => $defaultAvatars,
            'count' => $defaultAvatars->count(),
        ]);
    }

    public function uploadDefaultAvatar(Request $request)
    {
        if (! $request->hasFile('avatar')) {
            return response()->json([
                'message' => 'No avatar files uploaded',
                'errors' => ['avatar' => ['At least one avatar file is required']],
            ], 422);
        }

        $defaultPath = public_path('avatars/defaults');

        if (! file_exists($defaultPath)) {
            mkdir($defaultPath, 0755, true);
        }

        $existingFiles = glob($defaultPath . '/avatar-*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        $numbers = [];

        foreach ($existingFiles as $file) {
            preg_match('/avatar-(\d+)/', basename($file), $matches);
            if (isset($matches[1])) {
                $numbers[] = (int) $matches[1];
            }
        }

        $nextNumber = ! empty($numbers) ? max($numbers) + 1 : 1;

        $files = $request->file('avatar');
        if (! is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (! $file->isValid()) {
                return response()->json([
                    'message' => 'Invalid file uploaded',
                    'errors' => ['avatar' => ['One or more files are invalid']],
                ], 422);
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return response()->json([
                    'message' => 'Invalid file type',
                    'errors' => ['avatar' => ['Only jpg, jpeg, png, gif, and webp files are allowed']],
                ], 422);
            }

            if ($file->getSize() > 2048 * 1024) {
                return response()->json([
                    'message' => 'File too large',
                    'errors' => ['avatar' => ['Each file must be less than 2MB']],
                ], 422);
            }
        }

        $uploadedAvatars = [];

        foreach ($files as $file) {
            $extension = $file->getClientOriginalExtension();
            $filename = "avatar-{$nextNumber}.{$extension}";
            $relativePath = "avatars/defaults/{$filename}";
            $file->move($defaultPath, $filename);

            $avatar = Avatar::create([
                'filename' => $filename,
                'path' => $relativePath,
                'is_default' => true,
            ]);

            $uploadedAvatars[] = [
                'id' => $avatar->id,
                'filename' => $filename,
                'url' => asset($relativePath),
            ];

            $nextNumber++;
        }

        return response()->json([
            'message' => count($uploadedAvatars) . ' default avatar(s) uploaded successfully',
            'avatars' => $uploadedAvatars,
        ], 201);
    }
}
