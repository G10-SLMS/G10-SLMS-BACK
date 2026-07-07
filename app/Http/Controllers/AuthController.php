<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Avatar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'role' => 'nullable|string|in:admin,trainer,student',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'student',
        ]);

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
     * Update user profile
     * PUT /api/profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'avatar' => 'nullable|image|max:2048',
            'default_avatar' => 'nullable|string|max:255',
            'current_password' => 'nullable|required_with:password|current_password',
            'password' => ['nullable', 'confirmed', PasswordRule::min(8)],
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        // Handle default avatar selection
        if ($request->has('default_avatar')) {
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
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

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

        // Save the uploaded file
        $extension = $request->file('avatar')->getClientOriginalExtension();
        $filename = "avatar-{$nextNumber}.{$extension}";
        $relativePath = "avatars/defaults/{$filename}";
        $request->file('avatar')->move($defaultPath, $filename);

        // Create avatar record in database
        $avatar = Avatar::create([
            'filename' => $filename,
            'path' => $relativePath,
            'is_default' => true,
        ]);

        return response()->json([
            'message' => 'Default avatar uploaded successfully',
            'avatar' => [
                'id' => $avatar->id,
                'filename' => $filename,
                'url' => asset($relativePath),
            ]
        ], 201);
    }
}
