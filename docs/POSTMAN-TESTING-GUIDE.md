# Postman Testing Guide - Forgot Password Flow

This guide provides step-by-step instructions for testing the forgot password and reset password functionality using Postman.

## Prerequisites

1. **Postman** installed on your machine
2. **Laravel server running** on `http://localhost:8000` (or your configured URL)
3. **Test user account** in the database (e.g., `ly.sarl@student.passerellesnumeriques.org`)

## Step 1: Request Password Reset (Forgot Password)

### Request Details:
- **Method:** `POST`
- **URL:** `http://localhost:8000/api/forgot-password`
- **Headers:**
  - `Content-Type: application/json`
  - `Accept: application/json`

### Body (raw JSON):
```json
{
    "email": "ly.sarl@student.passerellesnumeriques.org"
}
```

### Expected Response (Success - 200 OK):
```json
{
    "message": "Password reset link sent to your email"
}
```

### Expected Response (Error - 422 Validation Error):
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "The email field is required."
        ]
    }
}
```

### Important Notes:
- The API returns the same success message whether the email exists or not (security best practice)
- Check your email logs to see the reset link (since `MAIL_MAILER=log` is configured)

### How to View the Reset Link:

Since the mailer is set to `log`, check the Laravel log file:
```bash
# Windows
tail -f storage/logs/laravel.log

# Or open the log file
notepad storage/logs/laravel.log
```

Look for the email content which contains the reset link like:
```
https://example.com/reset-password?token=a9L20KDwYv...&email=ly.sarl@student.passerellesnumeriques.org
```

**Copy the token from this URL** - you'll need it for Step 2.

---

## Step 2: Reset Password

### Request Details:
- **Method:** `POST`
- **URL:** `http://localhost:8000/api/reset-password`
- **Headers:**
  - `Content-Type: application/json`
  - `Accept: application/json`

### Body (raw JSON):
```json
{
    "email": "ly.sarl@student.passerellesnumeriques.org",
    "token": "TOKEN_FROM_EMAIL",
    "password": "MyNewPassword123",
    "password_confirmation": "MyNewPassword123"
}
```

**Replace `TOKEN_FROM_EMAIL` with the actual token you got from the email log.**

### Expected Response (Success - 200 OK):
```json
{
    "message": "Password reset successfully"
}
```

### Expected Response (Error - 422 Validation Error):

**Invalid Token:**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "Invalid reset link"
        ]
    }
}
```

**Expired Token:**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "Reset link expired"
        ]
    }
}
```

**Password Mismatch:**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "password": [
            "The password confirmation does not match."
        ]
    }
}
```

---

## Step 3: Verify Password Reset

### Test Login with New Password:

- **Method:** `POST`
- **URL:** `http://localhost:8000/api/login`
- **Headers:**
  - `Content-Type: application/json`
  - `Accept: application/json`

### Body (raw JSON):
```json
{
    "email": "ly.sarl@student.passerellesnumeriques.org",
    "password": "MyNewPassword123"
}
```

### Expected Response (Success - 200 OK):
```json
{
    "user": {
        "id": 1,
        "name": "Ly Sarl",
        "email": "ly.sarl@student.passerellesnumeriques.org",
        "role": "student",
        "created_at": "2026-07-07T...",
        "updated_at": "2026-07-07T..."
    },
    "token": "1|abcdefghijklmnopqrstuvwxyz..."
}
```

If you can login with the new password, the reset was successful!

---

## Complete Postman Collection

You can import this collection into Postman for easy testing:

### Collection 1: Forgot Password Flow

#### Request 1: Request Password Reset
```json
{
    "info": {
        "name": "Forgot Password Flow",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "item": [
        {
            "name": "1. Request Password Reset",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    },
                    {
                        "key": "Accept",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"email\": \"ly.sarl@student.passerellesnumeriques.org\"\n}"
                },
                "url": {
                    "raw": "http://localhost:8000/api/forgot-password",
                    "protocol": "http",
                    "host": ["localhost"],
                    "port": "8000",
                    "path": ["api", "forgot-password"]
                }
            }
        },
        {
            "name": "2. Reset Password",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    },
                    {
                        "key": "Accept",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"email\": \"ly.sarl@student.passerellesnumeriques.org\",\n    \"token\": \"{{token}}\",\n    \"password\": \"MyNewPassword123\",\n    \"password_confirmation\": \"MyNewPassword123\"\n}"
                },
                "url": {
                    "raw": "http://localhost:8000/api/reset-password",
                    "protocol": "http",
                    "host": ["localhost"],
                    "port": "8000",
                    "path": ["api", "reset-password"]
                }
            }
        },
        {
            "name": "3. Login with New Password",
            "request": {
                "method": "POST",
                "header": [
                    {
                        "key": "Content-Type",
                        "value": "application/json"
                    },
                    {
                        "key": "Accept",
                        "value": "application/json"
                    }
                ],
                "body": {
                    "mode": "raw",
                    "raw": "{\n    \"email\": \"ly.sarl@student.passerellesnumeriques.org\",\n    \"password\": \"MyNewPassword123\"\n}"
                },
                "url": {
                    "raw": "http://localhost:8000/api/login",
                    "protocol": "http",
                    "host": ["localhost"],
                    "port": "8000",
                    "path": ["api", "login"]
                }
            }
        }
    ]
}
```

---

## Testing Scenarios

### Scenario 1: Valid Password Reset
1. Request reset with valid email
2. Copy token from email log
3. Reset password with valid token and matching passwords
4. Login with new password
5. **Expected:** Success at all steps

### Scenario 2: Invalid Email Format
```json
{
    "email": "invalid-email"
}
```
**Expected:** 422 Validation Error

### Scenario 3: Non-existent Email
```json
{
    "email": "nonexistent@example.com"
}
```
**Expected:** 200 OK (but no email sent - security feature)

### Scenario 4: Invalid Token
```json
{
    "email": "ly.sarl@student.passerellesnumeriques.org",
    "token": "invalid-token-123",
    "password": "MyNewPassword123",
    "password_confirmation": "MyNewPassword123"
}
```
**Expected:** 422 Error - "Invalid reset link"

### Scenario 5: Password Mismatch
```json
{
    "email": "ly.sarl@student.passerellesnumeriques.org",
    "token": "valid-token",
    "password": "MyNewPassword123",
    "password_confirmation": "DifferentPassword123"
}
```
**Expected:** 422 Error - "The password confirmation does not match."

### Scenario 6: Weak Password
```json
{
    "email": "ly.sarl@student.passerellesnumeriques.org",
    "token": "valid-token",
    "password": "short",
    "password_confirmation": "short"
}
```
**Expected:** 422 Error - "The password field must be at least 8 characters."

### Scenario 7: Expired Token
Wait 60+ minutes after requesting reset, then try to use the token.
**Expected:** 422 Error - "Reset link expired"

---

## Quick Reference: Postman Setup

### Environment Variables (Optional but Recommended):

Create a Postman environment with these variables:
- `base_url`: `http://localhost:8000`
- `test_email`: `ly.sarl@student.passerellesnumeriques.org`
- `test_password`: `MyNewPassword123`
- `token`: (will be filled after step 1)

### Using Environment Variables:

**Request 1:**
```
URL: {{base_url}}/api/forgot-password
Body: {"email": "{{test_email}}"}
```

**Request 2:**
```
URL: {{base_url}}/api/reset-password
Body: {
    "email": "{{test_email}}",
    "token": "{{token}}",
    "password": "{{test_password}}",
    "password_confirmation": "{{test_password}}"
}
```

**Request 3:**
```
URL: {{base_url}}/api/login
Body: {
    "email": "{{test_email}}",
    "password": "{{test_password}}"
}
```

---

## Troubleshooting

### Issue: "Token not found" error
**Solution:** Make sure you're using the exact token from the email log (it's hashed in the database)

### Issue: "Reset link expired" error
**Solution:** Tokens expire after 60 minutes. Request a new reset link.

### Issue: Email not received
**Solution:** 
1. Check `MAIL_MAILER=log` in `.env`
2. View `storage/logs/laravel.log` for the email content
3. For real emails, configure SMTP settings in `.env`

### Issue: 404 Not Found
**Solution:** Make sure the Laravel server is running:
```bash
php artisan serve
```

### Issue: 419 CSRF Token Mismatch (for web routes)
**Solution:** Add CSRF token to your request or use API routes instead

---

## Testing Checklist

- [ ] Request password reset with valid email
- [ ] View reset link in email log
- [ ] Copy token from email
- [ ] Reset password with valid token
- [ ] Verify password was changed in database
- [ ] Login with new password
- [ ] Test with invalid email format
- [ ] Test with non-existent email
- [ ] Test with invalid token
- [ ] Test with mismatched passwords
- [ ] Test with weak password
- [ ] Test token expiration (wait 60+ minutes)

---

## Additional Testing Tips

1. **Use Postman Collection Runner** to run all requests in sequence
2. **Set up test scripts** in Postman to automate verification
3. **Monitor the database** to verify tokens are created and deleted
4. **Check Laravel logs** for detailed error messages
5. **Use environment variables** to avoid hardcoding values

---

## Example Test Script (Postman)

Add this to the "Tests" tab of Request 1 to automatically save the token:

```javascript
// Extract token from response (if returned)
// Note: Token is in email log, not in response for security

// For testing purposes, you can manually set it:
pm.environment.set("token", "paste-token-here");
```

---

## Security Notes

1. **Never expose tokens in production logs** - This is only for testing
2. **Always use HTTPS in production** - Tokens should never be sent over HTTP
3. **Tokens are single-use** - They're deleted after successful reset
4. **All sessions are invalidated** - User must login again on all devices

---

## Support

If you encounter issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify database connection in `.env`
3. Ensure migrations have been run: `php artisan migrate`
4. Check mail configuration in `.env`