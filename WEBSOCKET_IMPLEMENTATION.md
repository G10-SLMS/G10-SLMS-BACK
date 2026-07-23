# WebSocket Implementation for Leave Request Notifications

## Overview
This implementation adds real-time WebSocket notifications using Laravel Reverb. When a student submits a leave request, trainers receive instant notifications without refreshing the page.

## Implementation Summary

### 1. Dependencies Installed
- **Laravel Reverb** (v1.11.0) - WebSocket server for Laravel

### 2. Configuration Files Modified

#### `.env`
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=1
REVERB_APP_KEY=local-app-key
REVERB_APP_SECRET=local-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

#### `config/broadcasting.php`
- Added 'reverb' connection configuration
- Set default broadcaster to use Reverb

### 3. New Files Created

#### `app/Events/LeaveRequestCreated.php`
- Event class that implements `ShouldBroadcast`
- Broadcasts on both 'trainers' and 'admins' private channels
- Sends leave request data including:
  - Student name and ID
  - Leave type
  - Start and end dates/times
  - Reason
  - Status
  - Creation timestamp

#### `routes/channels.php`
- Defines the 'trainers' private channel
- Defines the 'admins' private channel
- Authorization callbacks ensure only authenticated users with correct role and `is_active` status can subscribe

#### `tests/Feature/LeaveRequestBroadcastTest.php`
- Test to verify event broadcasting works correctly
- Test to verify trainers channel authorization rules
- Test to verify admins channel authorization rules

### 4. Modified Files

#### `app/Http/Controllers/LeaveRequestController.php`
- Added import for `LeaveRequestCreated` event
- Added `broadcast(new LeaveRequestCreated($leave))` after successful leave request creation
- Event is triggered in the `store()` method after the leave request is created

## How It Works

1. **Student submits a leave request** via `POST /api/leave-requests`
2. **LeaveRequestController stores the request** in the database
3. **Event is broadcast** via `broadcast(new LeaveRequestCreated($leave))`
4. **Laravel Reverb** pushes the event to all connected clients on both channels
5. **Trainers and Admins receive real-time notification** without page refresh

## Channel Authorization

### Trainers Channel
The private 'trainers' channel is protected:
- ✅ **Trainers** with `is_active = true` can subscribe
- ❌ **Students** cannot subscribe
- ❌ **Admins** cannot subscribe (only trainers)
- ❌ **Inactive trainers** cannot subscribe

### Admins Channel
The private 'admins' channel is protected:
- ✅ **Admins** with `is_active = true` can subscribe
- ❌ **Students** cannot subscribe
- ❌ **Trainers** cannot subscribe (only admins)
- ❌ **Inactive admins** cannot subscribe

## Testing

### Run the broadcast tests:
```bash
php artisan test tests/Feature/LeaveRequestBroadcastTest.php
```

### Expected output:
```
PASS  Tests\Feature\LeaveRequestBroadcastTest
  ✓ leave request creation broadcasts event
  ✓ trainers channel authorization
  ✓ admins channel authorization

Tests: 3 passed (10 assertions)
```

## Frontend Integration (Example)

To receive notifications in the frontend, use Laravel Echo with Reverb:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth',
});

// Subscribe to the trainers channel (only works for authenticated trainers)
const trainerChannel = window.Echo.private('trainers');

trainerChannel.listen('.leave-request.created', (e) => {
    console.log('New leave request (Trainer):', e);
    // Display notification to trainer
});

// Subscribe to the admins channel (only works for authenticated admins)
const adminChannel = window.Echo.private('admins');

adminChannel.listen('.leave-request.created', (e) => {
    console.log('New leave request (Admin):', e);
    // Display notification to admin
});
```

## Running the WebSocket Server

To start the Laravel Reverb WebSocket server:

```bash
php artisan reverb:start
```

Or add it to your development scripts in `composer.json`:
```json
"scripts": {
    "reverb": [
        "php artisan reverb:start"
    ]
}
```

## Acceptance Criteria Met

✅ **Student submits a leave request** - Implemented in `LeaveRequestController::store()`
✅ **Laravel broadcasts the event** - `LeaveRequestCreated` event implements `ShouldBroadcast` and broadcasts to both trainers and admins
✅ **Trainers receive instant notifications** - Private 'trainers' channel with proper authorization
✅ **Admins receive instant notifications** - Private 'admins' channel with proper authorization
✅ **No page refresh required** - Real-time WebSocket communication via Laravel Reverb

## Security

- Private channel ensures only authorized users can listen
- Authentication required via Laravel Sanctum
- Role-based access control (trainers only)
- Active status check for trainers

## Next Steps

1. Start the Reverb server: `php artisan reverb:start`
2. Configure frontend to connect to WebSocket
3. Implement notification UI for trainers
4. Test with multiple trainers connected simultaneously