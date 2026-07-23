<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel for trainers to receive leave request notifications
Broadcast::channel('trainers', function ($user) {
    // Only authenticated trainers can subscribe to this channel
    return $user->role === 'trainer' && $user->is_active;
});

// Private channel for admins to receive leave request notifications
Broadcast::channel('admins', function ($user) {
    // Only authenticated admins can subscribe to this channel
    return $user->role === 'admin' && $user->is_active;
});

// Private channel for students to receive their own leave request notifications
Broadcast::channel('student.{userId}', function ($user, $userId) {
    // Only the student who created the request can subscribe to their own channel
    return $user->id === (int) $userId && $user->is_active;
});
