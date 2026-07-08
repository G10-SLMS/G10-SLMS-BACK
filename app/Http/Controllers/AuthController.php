<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Avatar;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Register
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['role'] = $data['role'] ?? 'student';

        $user = User::create($data);

        // Assign default avatar based on gender
        if ($request->gender === 'female') {
            $user->avatar_id = 87;
        } elseif ($request->gender === 'male') {
            $user->avatar_id = 88;
        }
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    // Login
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

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Request password reset
     * POST /api/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If an account with that email exists, a password reset link has been sent.',
        ]);
    }

    // Reset Password
    // public function resetPassword(Request $request)
    // {
    //     $request->validate([
    //         'token' => 'required|string',
    //         'email' => 'required|email',
    //         'password' => 'required|string|min:8|confirmed',
    //     ]);

    //     $status = Password::reset(
    //         $request->only('email', 'password', 'password_confirmation', 'token'),
    //         function (User $user, string $password) {
    //             $user->forceFill(['password' => Hash::make($password), 'remember_token' => null])->setRememberToken(Str::random(60));
    //             $user->save();
    //             $user->tokens()->delete();

    //             event(new PasswordReset($user));
    //         }
    //     );

    //     if ($status !== Password::PASSWORD_RESET) {
    //         return response()->json(['message' => __($status)], 422);
    //     }

    //     return response()->json(['message' => 'Password has been reset successfully.']);
    // }

    /**
     * Reset password with token
     * POST /api/reset-password
     */
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
                
                // Delete all Sanctum tokens to log user out from all devices
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successfully',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Update user profile
     * PUT /api/profile
     */
    // public function updateProfile(Request $request)
    // {
    //     $user = $request->user();

    //     $request->validate([
    //         'name' => 'sometimes|string|max:255',
    //         'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
    //         'id_card' => 'nullable|integer|min:0',
    //         'generation' => 'nullable|string|max:255',
    //         'avatar' => 'nullable|image|max:2048',
    //         'default_avatar' => 'nullable|string|max:255',
    //         'avatar_id' => 'nullable|integer|exists:avatars,id',
    //         'current_password' => 'nullable|required_with:password|current_password',
    //         'password' => ['nullable', 'confirmed', PasswordRule::min(8)],
    //     ]);

    //     if ($request->has('name')) {
    //         $user->name = $request->name;
    //     }

    //     if ($request->has('email')) {
    //         $user->email = $request->email;
    //     }

    //     if ($request->has('id_card')) {
    //         $user->id_card = $request->id_card;
    //     }

    //     if ($request->has('generation')) {
    //         $user->generation = $request->generation;
    //     }

    //     // Handle avatar_id update (direct ID)
    //     if ($request->has('avatar_id')) {
    //         $user->avatar_id = $request->avatar_id;
    //     }
    //     // Handle default avatar selection
    //     elseif ($request->has('default_avatar')) {
    //         $defaultAvatarPath = 'avatars/defaults/' . $request->default_avatar;

    //         // Verify the default avatar exists in the database
    //         $avatar = Avatar::where('path', $defaultAvatarPath)
    //             ->where('is_default', true)
    //             ->first();

    //         if ($avatar) {
    //             $user->avatar_id = $avatar->id;
    //         }
    //     }
    //     // Handle custom avatar upload
    //     elseif ($request->hasFile('avatar')) {
    //         // Delete old avatar record if exists
    //         if ($user->avatar_id) {
    //             $oldAvatar = Avatar::find($user->avatar_id);
    //             if ($oldAvatar && !$oldAvatar->is_default) {
    //                 // Delete the file
    //                 if (file_exists(public_path('storage/' . $oldAvatar->path))) {
    //                     unlink(public_path('storage/' . $oldAvatar->path));
    //                 }
    //                 $oldAvatar->delete();
    //             }
    //         }

    //         // Create new avatar record
    //         $path = $request->file('avatar')->store('avatars', 'public');
    //         $avatar = Avatar::create([
    //             'filename' => basename($path),
    //             'path' => $path,
    //             'is_default' => false,
    //         ]);

    //         $user->avatar_id = $avatar->id;
    //     }

    //     if ($request->has('password')) {
    //         $user->password = Hash::make($request->password);
    //     }

    //     $user->save();

    //     return response()->json([
    //         'user' => $user->load('avatar'),
    //         'message' => 'Profile updated successfully',
    //     ]);
    // }

    /**
     * Get authenticated user
     * GET /api/profile
     */
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Upload new avatar
        if ($request->hasFile('avatar')) {

            // Delete old avatar if it exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        // Hash password only if provided
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

    /**
     * Get all default avatars
     * GET /api/default-avatars
     */
    public function getDefaultAvatars()
    {
        $defaultAvatars = Avatar::where('is_default', true)
            ->get()
            ->map(function ($avatar) {
                return [
                    'id' => $avatar->id,
                    'filename' => $avatar->filename,
                    'url' => asset($avatar->path),
                ];
            });

        return response()->json([
            'avatars' => $defaultAvatars,
            'count' => $defaultAvatars->count(),
        ]);
    }

    /**
     * Upload new default avatar (Admin only)
     * POST /api/admin/default-avatars
     */
    public function uploadDefaultAvatar(Request $request)
    {
        // Validate that at least one avatar file is present
        if (!$request->hasFile('avatar')) {
            return response()->json([
                'message' => 'No avatar files uploaded',
                'errors' => ['avatar' => ['At least one avatar file is required']]
            ], 422);
        }

        $defaultPath = public_path('avatars/defaults');

        // Create directory if it doesn't exist
        if (!file_exists($defaultPath)) {
            mkdir($defaultPath, 0755, true);
        }

        // Get the next available avatar number
        $existingFiles = glob($defaultPath . '/avatar-*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        $nextNumber = 1;

        if (!empty($existingFiles)) {
            $numbers = [];
            foreach ($existingFiles as $file) {
                preg_match('/avatar-(\d+)/', basename($file), $matches);
                if (isset($matches[1])) {
                    $numbers[] = (int) $matches[1];
                }
            }
            $nextNumber = !empty($numbers) ? max($numbers) + 1 : 1;
        }

        $uploadedAvatars = [];

        // Get files - handle both single file and multiple files
        $files = $request->file('avatar');
        
        // Convert single file to array
        if (!is_array($files)) {
            $files = [$files];
        }

        // Validate each file
        foreach ($files as $file) {
            if (!$file->isValid()) {
                return response()->json([
                    'message' => 'Invalid file uploaded',
                    'errors' => ['avatar' => ['One or more files are invalid']]
                ], 422);
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return response()->json([
                    'message' => 'Invalid file type',
                    'errors' => ['avatar' => ['Only jpg, jpeg, png, gif, and webp files are allowed']]
                ], 422);
            }

            if ($file->getSize() > 2048 * 1024) { // 2MB in bytes
                return response()->json([
                    'message' => 'File too large',
                    'errors' => ['avatar' => ['Each file must be less than 2MB']]
                ], 422);
            }
        }

        // Process each uploaded file
        foreach ($files as $file) {
            // Save the uploaded file
            $extension = $file->getClientOriginalExtension();
            $filename = "avatar-{$nextNumber}.{$extension}";
            $relativePath = "avatars/defaults/{$filename}";
            $file->move($defaultPath, $filename);

            // Create avatar record in database
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
