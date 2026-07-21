<?php

use App\Http\Controllers\AuthController;
<<<<<<< HEAD
use App\Http\Controllers\CommentController;
=======
use App\Http\Controllers\AttachmentController;

>>>>>>> 9cc2e366d5a29f79eca27f549cc5a7b9dd5844b9
use App\Http\Controllers\LeaveHistoryController;

use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\NotificationController;

use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes
Route::get('/default-avatars', [AuthController::class, 'getDefaultAvatars']);

// Admin routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::post('/admin/default-avatars', [AuthController::class, 'uploadDefaultAvatar']);
});

// Authentication routes (Sanctum)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authentication routes (sanctum) -> forgot password
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Social login (Google / GitHub)
Route::middleware('cors')->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['google', 'github']);
    Route::post('/auth/{provider}', [SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['google', 'github']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // Notifications: Student/Trainer/Admin (each sees only their own)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);


    // Student only
    Route::middleware('role:student')->group(function () {
        Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
        Route::put('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update']);
        Route::delete('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy']);
        Route::post('/leave-requests/{leaveRequest}/attachments', [AttachmentController::class, 'store'])
            ->name('leave-requests.attachments.store');
    });

    // Trainer/Admin only
    Route::middleware('role:trainer,admin')->group(function () {
        Route::post('/approve/{leaveRequest}', [LeaveRequestController::class, 'approve']);
        Route::post('/reject/{leaveRequest}', [LeaveRequestController::class, 'reject']);
    });



    // Shared: Student/Trainer/Admin
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show']);

    // Comment routes 
    Route::get('/comments', [CommentController::class, 'index']);
    Route::post('/comments', [CommentController::class, 'store']);
    Route::get('/comments/{comment}', [CommentController::class, 'show']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);


    // Student Leave History
    Route::middleware('role:student')->group(function () {
        Route::get('/leave-history', [LeaveHistoryController::class, 'index']);
        Route::get('/leave-history/{id}', [LeaveHistoryController::class, 'show']);
    });
});

// Leave Type API
Route::get('/leave-types', [LeaveTypeController::class, 'index']);
Route::get('/leave-types/{id}', [LeaveTypeController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:admin,student'])->group(function () {
    Route::post('/leave-types', [LeaveTypeController::class, 'store']);
    Route::put('/leave-types/{leaveType}', [LeaveTypeController::class, 'update']);
    Route::delete('/leave-types/{leaveType}', [LeaveTypeController::class, 'destroy']);
});
