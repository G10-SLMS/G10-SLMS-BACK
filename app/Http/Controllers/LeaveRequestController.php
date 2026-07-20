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
      * Student: update own pending request
      * Trainer/Admin: approve/reject any request
      */
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
   
}
