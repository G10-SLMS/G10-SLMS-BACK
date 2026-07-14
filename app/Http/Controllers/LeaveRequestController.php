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
        $query = LeaveRequest::with(['leaveType', 'user', 'approver']);

        if ($request->user()->role === 'student') {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->latest()->paginate(10)
        );
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
        if ($request->user()->role === 'student' && $leaveRequest->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($leaveRequest->load(['leaveType', 'user', 'approver', 'comments', 'attachments']));
    }

    /**
     * PUT /api/leave-requests/{id}
     * Student only, before approval
     */
    public function update(UpdateLeaveRequest $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot edit a request that has already been reviewed.'
            ], 422);
        }

        $leaveRequest->update($request->validated());

        return response()->json($leaveRequest->load('leaveType'));
    }

    /**
     * DELETE /api/leave-requests/{id}
     * Student only, cancel pending request
     */
    public function destroy(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot cancel a request that has already been reviewed.'
            ], 422);
        }

        $leaveRequest->delete();

        return response()->json(['message' => 'Leave request cancelled'], 200);
    }

    /**
     * POST /api/approve/{id}
     * Trainer only
     */
    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            // 'reviewed_by' => $request->user()->id,
            // 'reviewed_at' => now(),
        ]);

        return response()->json($leaveRequest->load('leaveType'));
    }

    /**
     * POST /api/reject/{id}
     * Trainer only
     */
    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'This request has already been reviewed.'], 422);
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,

            // 'reviewed_by' => $request->user()->id,
            // 'reviewed_at' => now(),
        ]);

        return response()->json($leaveRequest->load('leaveType'));
    }
}
