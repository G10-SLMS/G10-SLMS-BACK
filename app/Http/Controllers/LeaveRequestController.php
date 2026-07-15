<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Requests\UpdateLeaveRequest;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * GET /api/leave-requests
     * Trainer/Admin: all requests | Student: own only
     */
    public function index(Request $request)
    {
        $query = LeaveRequest::with(['leaveType', 'user', 'reviewer']);

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

    /**
     * POST /api/leave-requests
     * Student only
     */
    public function store(StoreLeaveRequest $request)
    {
        $leave = LeaveRequest::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json($leave->load('leaveType'), 201);
    }

    /**
     * GET /api/leave-requests/{id}
     * Student/Trainer/Admin
     */
    public function show(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();

        // if ($request->user()->role === 'student' && $leaveRequest->user_id !== $request->user()->id) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        if ($user->role === 'student' && $leaveRequest->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this leave request.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request retrieved successfully.',
            'data' => $leaveRequest->load(['leaveType', 'user', 'reviewer', 'comments', 'attachments']),
        ]);
    }

    /**
     * PUT /api/leave-requests/{id}
     * Student only, before approval
     */
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
            'data' => $leaveRequest->load(['leaveType', 'user', 'reviewer']),
        ]);
    }

    /**
     * DELETE /api/leave-requests/{id}
     * Student only, cancel pending request
     *  * - Verifies ownership (403 if not the student who created it)
     * - Only allowed while status is 'pending' (422 otherwise)
     * - Returns 204 No Content on success (no response body)
     */

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
     * POST /api/approve/{id}
     * Trainer only
     */
    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been reviewed.',
            ], 422);
        }

        $leaveRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request approved successfully.',
            'data' => $leaveRequest->load(['leaveType', 'user', 'reviewer']),
        ]);
    }

    /**
     * POST /api/reject/{id}
     * Trainer only
     */
    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been reviewed.',
            ], 422);
        }

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'review_note.required' => 'Please provide a reason for rejecting this request.',
        ]);

        $leaveRequest->update([
            'status' => 'rejected',
            // 'approved_by' => $request->user()->id,

            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request rejected successfully.',
            'data' => $leaveRequest->load(['leaveType', 'user', 'reviewer']),
        ]);
    }
}
