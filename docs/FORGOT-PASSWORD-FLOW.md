# Forgot Password Flow Documentation

## Overview

This document describes the complete forgot password implementation with real email functionality for the G10-SLMS-BACK application.

## Architecture

The forgot password flow consists of two main parts:
1. **API Routes** - For SPA/JavaScript frontend applications
2. **Web Routes** - For traditional Blade template views

## Database Structure

### password_reset_tokens Table

```sql
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    INDEX(email)
);
```

## API Endpoints

### 1. Request Password Reset
**Endpoint:** `POST /api/forgot-password`

**Request:**
```json
{
    "email": "user@example.com"
}
```

**Response (Success):**
```json
{
    "message": "Password reset link sent to your email"
}
```

**Response (Error):**
```json
{
    "message": "The provided credentials are incorrect.",
    "errors": {
        "email": ["The provided credentials are incorrect."]
    }
}
```

### 2. Reset Password
**Endpoint:** `POST /api/reset-password`

**Request:**
```json
{
    "email": "user@example.com",
    "token": "random-generated-token",
    "password": "new-password",
    "password_confirmation": "new-password"
}
```

**Response (Success):**
```json
{
    "message": "Password reset successfully"
}
```

**Response (Error):**
```json
{
    "message": "The provided credentials are incorrect.",
    "errors": {
        "email": ["Invalid reset link"]
    }
}
```

## Web Routes (Blade Views)

### 1. Forgot Password Page
**URL:** `GET /forgot-password`

Displays a form where users can enter their email address to request a password reset.

### 2. Reset Password Page
**URL:** `GET /reset-password?token=xxx&email=xxx`

Displays a form where users can enter a new password using the token from their email.

## Email Configuration

### Current Configuration (.env)

```env
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Production Email Setup

For production, update your `.env` file with real SMTP credentials:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

## Email Template

When a user requests a password reset, they receive an email with:
- **Subject:** Reset Password Notification
- **Content:**
  - Greeting message
  - Explanation of why they received the email
  - "Reset Password" button linking to: `https://example.com/reset-password?token=xxx&email=xxx`
  - Expiration notice (60 minutes)
  - Security notice

## Security Features

1. **Token Hashing:** Reset tokens are hashed before storage in the database
2. **Token Expiration:** Tokens expire after 60 minutes (configurable in `config/auth.php`)
3. **Single Use:** Tokens are deleted after successful password reset
4. **Device Logout:** All existing Sanctum tokens are deleted, forcing re-authentication on all devices
5. **Rate Limiting:** Users can only request a reset every 10 seconds (throttle setting)

## Flow Diagram

```
User
  │
  ├─→ Clicks "Forgot Password?"
  │
  ├─→ Enters email: ly.sarl@student.passerellesnumeriques.org
  │
  ├─→ POST /api/forgot-password
  │   {
  │       "email": "ly.sarl@student.passerellesnumeriques.org"
  │   }
  │
  ├─→ Backend validates email
  │
  ├─→ Generates secure random token
  │
  ├─→ Hashes token and stores in password_reset_tokens table
  │
  ├─→ Creates reset URL:
  │   https://example.com/reset-password?token=xxx&email=xxx
  │
  ├─→ Sends email via SMTP
  │
  ├─→ User receives email and clicks "Reset Password" button
  │
  ├─→ Browser opens reset password page with token and email in URL
  │
  ├─→ User enters new password and confirmation
  │
  ├─→ POST /api/reset-password
  │   {
  │       "email": "ly.sarl@student.passerellesnumeriques.org",
  │       "token": "xxx",
  │       "password": "MyNewPassword123",
  │       "password_confirmation": "MyNewPassword123"
  │   }
  │
  ├─→ Backend validates:
  │   ✓ Email exists
  │   ✓ Token exists and matches
  │   ✓ Token not expired
  │   ✓ Password confirmation matches
  │   ✓ Password meets strength requirements
  │
  ├─→ Hashes new password
  │
  ├─→ Updates users.password
  │
  ├─→ Deletes password_reset_tokens record
  │
  ├─→ Deletes all Sanctum tokens (logs out all devices)
  │
  └─→ Returns success message
```

## Configuration Files

### config/auth.php

The password reset configuration is already set up:

```php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60, // minutes
        'throttle' => 10, // seconds
    ],
],
```

## Files Created/Modified

### Created Files:
1. `database/migrations/2026_07_08_000000_create_password_reset_tokens_table.php`
2. `app/Notifications/ResetPasswordNotification.php`
3. `resources/views/auth/forgot-password.blade.php`
4. `resources/views/auth/reset-password.blade.php`
5. `docs/FORGOT-PASSWORD-FLOW.md` (this file)

### Modified Files:
1. `app/Http/Controllers/AuthController.php` - Added `resetPassword()` method
2. `app/Models/User.php` - Added `sendPasswordResetNotification()` method
3. `routes/api.php` - Added `/api/reset-password` route
4. `routes/web.php` - Updated `/reset-password` route to accept query parameters

## Testing

### Manual Testing Steps

1. **Test Forgot Password Request:**
   ```bash
   curl -X POST http://localhost/api/forgot-password \
     -H "Content-Type: application/json" \
     -d '{"email":"ly.sarl@student.passerellesnumeriques.org"}'
   ```

2. **Check Email Log:**
   ```bash
   # If using log mailer, check storage/logs/laravel.log
   tail -f storage/logs/laravel.log
   ```

3. **Test Password Reset:**
   ```bash
   curl -X POST http://localhost/api/reset-password \
     -H "Content-Type: application/json" \
     -d '{
       "email":"ly.sarl@student.passerellesnumeriques.org",
       "token":"TOKEN_FROM_EMAIL",
       "password":"NewPassword123",
       "password_confirmation":"NewPassword123"
     }'
   ```

4. **Test Web Interface:**
   - Visit: `http://localhost/forgot-password`
   - Enter email and submit
   - Check email for reset link
   - Click link and reset password

## Example User

**Test Account:**
- **Name:** Ly Sarl
- **Email:** ly.sarl@student.passerellesnumeriques.org
- **Role:** Student

## Troubleshooting

### Email Not Sending
1. Check `.env` mail configuration
2. Verify SMTP credentials are correct
3. Check `storage/logs/laravel.log` for errors
4. Ensure mail server is running

### Token Not Working
1. Verify token hasn't expired (60 minutes)
2. Check `password_reset_tokens` table for the token
3. Ensure token hasn't been used already (it's deleted after use)

### Migration Errors
If the `password_reset_tokens` table already exists:
```bash
# The table was already created, no need to run migration again
# If you need to reset it:
php artisan migrate:rollback --step=1
php artisan migrate
```

## Security Considerations

1. **Never expose whether an email exists** - The API returns the same message whether the email exists or not
2. **Tokens are single-use** - Deleted immediately after successful reset
3. **Short expiration** - Tokens expire in 60 minutes
4. **Rate limiting** - Prevents abuse with throttle setting
5. **All devices logged out** - Sanctum tokens are deleted after password change
6. **Password hashing** - Uses bcrypt with 12 rounds (configurable via BCRYPT_ROUNDS)

## Next Steps

1. Configure real SMTP credentials in production
2. Test the complete flow with a real email address
3. Customize email template branding if needed
4. Add rate limiting to prevent abuse
5. Consider adding CAPTCHA to prevent spam
6. Implement password strength requirements in frontend validation

## Support

For issues or questions, refer to the Laravel documentation:
- [Laravel Password Reset](https://laravel.com/docs/11.x/authentication#password-reset)
- [Laravel Notifications](https://laravel.com/docs/11.x/notifications)
- [Laravel Mail](https://laravel.com/docs/11.x/mail)