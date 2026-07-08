<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Avatar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     * POST /api/register
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'gender' => [
                'required',
                'string',
                Rule::in(['male', 'female', 'other']),
            ],
            'id_card' => 'nullable|integer|min:0',
            'generation' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'role' => 'nullable|string|in:admin,trainer,student',
        ]);

        $user = User::create([
            'name' => $request->name,
            'gender' => $request->gender,
            'id_card' => $request->id_card,
            'generation' => $request->generation,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'student',
        ]);

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

    /**
     * Login user
     * POST /api/login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout user
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Request password reset
     * POST /api/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to your email',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

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
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'id_card' => 'nullable|integer|min:0',
            'generation' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|max:2048',
            'default_avatar' => 'nullable|string|max:255',
            'avatar_id' => 'nullable|integer|exists:avatars,id',
            'current_password' => 'nullable|required_with:password|current_password',
            'password' => ['nullable', 'confirmed', PasswordRule::min(8)],
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('id_card')) {
            $user->id_card = $request->id_card;
        }

        if ($request->has('generation')) {
            $user->generation = $request->generation;
        }

        // Handle avatar_id update (direct ID)
        if ($request->has('avatar_id')) {
            $user->avatar_id = $request->avatar_id;
        }
        // Handle default avatar selection
        elseif ($request->has('default_avatar')) {
            $defaultAvatarPath = 'avatars/defaults/' . $request->default_avatar;

            // Verify the default avatar exists in the database
            $avatar = Avatar::where('path', $defaultAvatarPath)
                ->where('is_default', true)
                ->first();

            if ($avatar) {
                $user->avatar_id = $avatar->id;
            }
        }
        // Handle custom avatar upload
        elseif ($request->hasFile('avatar')) {
            // Delete old avatar record if exists
            if ($user->avatar_id) {
                $oldAvatar = Avatar::find($user->avatar_id);
                if ($oldAvatar && !$oldAvatar->is_default) {
                    // Delete the file
                    if (file_exists(public_path('storage/' . $oldAvatar->path))) {
                        unlink(public_path('storage/' . $oldAvatar->path));
                    }
                    $oldAvatar->delete();
                }
            }

            // Create new avatar record
            $path = $request->file('avatar')->store('avatars', 'public');
            $avatar = Avatar::create([
                'filename' => basename($path),
                'path' => $path,
                'is_default' => false,
            ]);

            $user->avatar_id = $avatar->id;
        }

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'user' => $user->load('avatar'),
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * Get authenticated user
     * GET /api/profile
     */
    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
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
