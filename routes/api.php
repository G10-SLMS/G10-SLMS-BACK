<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes
Route::get('/default-avatars', [AuthController::class, 'getDefaultAvatars']);

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('/admin/default-avatars', [AuthController::class, 'uploadDefaultAvatar']);
});

// Authentication routes (Sanctum)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authentication routes (sanctum) -> forgot password
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Social login (Google / GitHub)
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'github']);
Route::post('/auth/{provider}', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'github']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
});