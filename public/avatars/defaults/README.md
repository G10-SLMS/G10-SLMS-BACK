# Default Avatars

This directory should contain 20 default avatar images for users to choose from.

## Requirements

- **Format**: JPG, JPEG, PNG, GIF, or WebP
- **Size**: 200x200 pixels (recommended)
- **File size**: Under 100KB each
- **Naming**: Use descriptive names like `avatar-1.jpg`, `avatar-2.jpg`, etc.

## How to Add Default Avatars

### Option 1: Download Free Avatars

You can download free avatar images from:
- https://www.flaticon.com/search?word=avatar
- https://www.iconfinder.com/search?q=avatar
- https://dicebear.com/ (API for generating avatars)

### Option 2: Generate Avatars with DiceBear API

Visit https://www.dicebear.com/ and use their API to generate avatars:

Example URLs:
```
https://api.dicebear.com/7.x/avataaars/svg?seed=1
https://api.dicebear.com/7.x/avataaars/svg?seed=2
...
https://api.dicebear.com/7.x/avataaars/svg?seed=20
```

Download and save them as:
- avatar-1.jpg
- avatar-2.jpg
- ...
- avatar-20.jpg

### Option 3: Use Placeholder Images

For testing, you can use placeholder images or create simple colored squares with initials.

## Testing the API

Once you have 20 images in this directory:

1. **List all default avatars**:
   ```
   GET http://127.0.0.1:8000/api/default-avatars
   ```

2. **Select a default avatar for a user**:
   ```
   PUT http://127.0.0.1:8000/api/profile
   Body (form-data):
   - default_avatar: avatar-1.jpg
   ```

## Current Status

This directory is ready for avatar images. Add 20 image files here to complete the default avatar system.