<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Attachment;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Requests\UpdateLeaveRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request)
    {
        $query = LeaveRequest::with(['leaveType', 'user.avatar', 'reviewer', 'attachments']);

        // Students can only see their own requests
        if ($request->user()->role === 'student') {
            $query->where('user_id', $request->user()->id);
        }

        // Search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                // Search by leave request ID (if search is numeric)
                if (is_numeric($search)) {
                    $q->orWhere('id', $search);
                }

                // Search by student name via user relationship
                $q->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'LIKE', '%' . $search . '%');
                });

                // Search by student ID via user relationship
                $q->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('student_id', 'LIKE', '%' . $search . '%');
                });
            });
        }

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter by leave type
        if ($leaveTypeId = $request->query('leave_type_id')) {
            $query->where('leave_type_id', $leaveTypeId);
        }

        // Filter by start date range (inclusive)
        if ($startDate = $request->query('start_date')) {
            $query->whereDate('start_date', '>=', $startDate);
        }

        // Filter by end date range (inclusive)
        if ($endDate = $request->query('end_date')) {
            $query->whereDate('end_date', '<=', $endDate);
        }

        // Filter by submission date range (inclusive)
        if ($submissionStartDate = $request->query('submission_start_date')) {
            $query->whereDate('created_at', '>=', $submissionStartDate);
        }

        if ($submissionEndDate = $request->query('submission_end_date')) {
            $query->whereDate('created_at', '<=', $submissionEndDate);
        }

        // Sorting
        $sortBy = $request->query('sort', 'latest'); // Default to latest (submission date)

        switch ($sortBy) {
            case 'start_date_asc':
                $query->orderBy('start_date', 'asc');
                break;
            case 'start_date_desc':
                $query->orderBy('start_date', 'desc');
                break;
            case 'end_date_asc':
                $query->orderBy('end_date', 'asc');
                break;
            case 'end_date_desc':
                $query->orderBy('end_date', 'desc');
                break;
            case 'submission_date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'submission_date_desc':
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 10;

        $leaveRequests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Leave requests retrieved successfully.',
            'data' => $leaveRequests->items(),
            'meta' => [
                'current_page' => $leaveRequests->currentPage(),
                'last_page' => $leaveRequests->lastPage(),
                'per_page' => $leaveRequests->perPage(),
                'total' => $leaveRequests->total(),
                'from' => $leaveRequests->firstItem(),
                'to' => $leaveRequests->lastItem(),
                'path' => $leaveRequests->path(),
                'first_page_url' => $leaveRequests->url(1),
                'last_page_url' => $leaveRequests->url($leaveRequests->lastPage()),
                'next_page_url' => $leaveRequests->nextPageUrl(),
                'prev_page_url' => $leaveRequests->previousPageUrl(),
            ],
        ]);
    }

    public function store(StoreLeaveRequest $request)
    {
        $leave = LeaveRequest::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        // Handle file attachments
        if ($request->hasFile('attachment')) {
            $files = $request->file('attachment');
            
            // Handle both single file and array of files
            if (!is_array($files)) {
                $files = [$files];
            }
            
            foreach ($files as $file) {
                $path = $file->store('attachments/leave-requests', 'public');
                
                Attachment::create([
                    'leave_request_id' => $leave->id,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $request->user()->id,
                    'is_verified' => false,
                ]);
            }
        }

        $this->notifications->notifyLeaveSubmitted($leave);
        
        // Reload the model with relationships to get fresh data
        $leave = $leave->fresh(['leaveType', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => "Leave request created successfully",
            'data' => $leave->toArray(),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $leaveRequest = LeaveRequest::with(['leaveType', 'user.avatar', 'reviewer', 'comments', 'attachments'])->find($id);

        if (!$leaveRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found.',
                'data' => null,
            ], 404);
        }

        // Student can only view their own requests
        if ($user->role === 'student' && $leaveRequest->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this leave request.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request retrieved successfully.',
            'data' => $leaveRequest,
        ]);
    }

    public function update(UpdateLeaveRequest $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();

        // Authorization check
        $isOwner = $user->id === $leaveRequest->user_id;
        $isTrainerOrAdmin = in_array($user->role, ['trainer', 'admin']);

        if (!$isOwner && !$isTrainerOrAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }

        // If trainer/admin is updating with status, handle approve/reject
        if ($isTrainerOrAdmin && $request->has('status')) {
            if ($leaveRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This request has already been reviewed.',
                ], 422);
            }

            $validated = $request->validated();

            $leaveRequest->update([
                'status' => $validated['status'],
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
            ]);

            $message = $validated['status'] === 'approved'
                ? 'Leave request approved successfully.'
                : 'Leave request rejected successfully.';

            if ($validated['status'] === 'approved') {
                $this->notifications->notifyLeaveApproved($leaveRequest, $user);
            } else {
                $this->notifications->notifyLeaveRejected($leaveRequest, $user);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $leaveRequest->load(['leaveType', 'user.avatar', 'reviewer']),
            ]);
        }

        // Student updating their own pending request
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Cannot edit a request that has already been reviewed ({$leaveRequest->status}).",
            ], 422);
        }

        $validated = $request->validated();

        // Handle student cancellation
        if (isset($validated['status']) && $validated['status'] === 'cancelled') {
            $leaveRequest->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $this->notifications->notifyLeaveCancelled($leaveRequest);

            return response()->json([
                'success' => true,
                'message' => 'Leave request cancelled successfully.',
                'data' => $leaveRequest->load(['leaveType', 'user.avatar', 'reviewer']),
            ]);
        }

        $leaveRequest->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Leave request updated successfully.',
            'data' => $leaveRequest->load(['leaveType', 'user.avatar', 'reviewer']),
        ]);
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this leave request.',
            ], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete a request that is already {$leaveRequest->status}.",
            ], 422);
        }

        $deletedId = $leaveRequest->id;
        $this->notifications->notifyLeaveCancelled($leaveRequest);
        $leaveRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leave request deleted successfully.',
            'data' => [
                'id' => $deletedId,
            ],
        ], 200);
    }

    /**
     * Download attachment file
     * GET /api/attachments/{attachment}/download
     */
    public function downloadAttachment(Request $request, Attachment $attachment)
    {
        $user = $request->user();

        // Check if user has access to this attachment's leave request
        $leaveRequest = $attachment->leaveRequest;
        
        if (!$leaveRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found.',
            ], 404);
        }

        // Students can only download their own attachments
        if ($user->role === 'student' && $leaveRequest->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to download this attachment.',
            ], 403);
        }

        // Check if file exists
        $filePath = storage_path('app/public/' . $attachment->path);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on server.',
            ], 404);
        }

        // Return file download response
        return response()->download($filePath, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $attachment->original_name . '"',
        ]);
    }
}
