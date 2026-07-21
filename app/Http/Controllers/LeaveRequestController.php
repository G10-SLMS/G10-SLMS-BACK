<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Requests\UpdateLeaveRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $query = LeaveRequest::with(['leaveType', 'user.avatar', 'reviewer']);

        if ($request->user()->role === 'student') {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $leaveRequests = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Leave requests retrieved successfully.',
            'data' => $leaveRequests->items(),
            'meta' => [
                'current_page' => $leaveRequests->currentPage(),
                'last_page' => $leaveRequests->lastPage(),
                'per_page' => $leaveRequests->perPage(),
                'total' => $leaveRequests->total(),
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

        $this->notifications->notifyLeaveSubmitted($leave);

        return response()->json($leave->load('leaveType'), 201);
    }

    public function show(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();

        if ($user->role === 'student' && $leaveRequest->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this leave request.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request retrieved successfully.',
            'data' => $leaveRequest->load(['leaveType', 'user.avatar', 'reviewer', 'comments', 'attachments']),
        ]);
    }

    public function update(UpdateLeaveRequest $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Cannot edit a request that has already been reviewed ({$leaveRequest->status}).",
            ], 422);
        }

        $leaveRequest->update($request->validated());

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


    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->review($request, $leaveRequest, 'approved', 'Leave request approved successfully.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->review($request, $leaveRequest, 'rejected', 'Leave request rejected successfully.');
    }

    protected function review(Request $request, LeaveRequest $leaveRequest, string $status, string $message): JsonResponse
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending leave requests can be reviewed.',
            ], 422);
        }

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:500'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $comment = $validated['comment'] ?? $validated['review_note'] ?? null;

        $leaveRequest->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $comment,
        ]);

        if ($status === 'approved') {
            $this->notifications->notifyLeaveApproved($leaveRequest, $request->user());
        } else {
            $this->notifications->notifyLeaveRejected($leaveRequest, $request->user());
        }

        $leaveRequest->load(['leaveType', 'user.avatar', 'reviewer']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->formatReviewedLeaveRequest($leaveRequest),
        ]);
    }

    protected function formatReviewedLeaveRequest(LeaveRequest $leaveRequest): array
    {
        $data = $leaveRequest->toArray();
        $data['comment'] = $leaveRequest->review_note;

        return $data;
    }
}
