<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    public function store(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to upload files for this leave request.',
            ], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Attachments can only be uploaded while the leave request is pending.',
            ], 422);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,doc,docx,png,jpg,jpeg,webp'],
        ], [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The selected item must be a valid file.',
            'file.max' => 'The file may not be greater than 5 MB.',
            'file.mimes' => 'Only PDF, DOC, DOCX, PNG, JPG, JPEG, and WEBP files are allowed.',
        ]);

        $file = $validated['file'];
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'attachment';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $filename = "{$baseName}-" . Str::uuid()->toString() . ".{$extension}";
        $directory = "supporting-documents/leave-requests/{$leaveRequest->id}";
        $path = $file->storePubliclyAs($directory, $filename, 'public');

        $attachment = Attachment::create([
            'leave_request_id' => $leaveRequest->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Supporting document uploaded successfully.',
            'data' => [
                'attachment' => $this->formatAttachment($attachment),
            ],
        ], 201);
    }

    protected function formatAttachment(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'leave_request_id' => $attachment->leave_request_id,
            'original_name' => $attachment->original_name,
            'path' => $attachment->path,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'url' => Storage::disk('public')->url($attachment->path),
            'uploaded_by' => $attachment->uploaded_by,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
