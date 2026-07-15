<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveHistoryController extends Controller
{
    /**
     * GET /api/leave-history
     * Student only - retrieve their own leave history
     */
    public function index(Request $request)
    {
        $leaveHistory = LeaveRequest::with(['leaveType', 'reviewer'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);
        
        return response()->json([
            'success' => true,
            'message' => 'Leave history retrieved successfully.',
            'data' => $leaveHistory->items(),
            'meta' => [
                'current_page' => $leaveHistory->currentPage(),
                'last_page' => $leaveHistory->lastPage(),
                'per_page' => $leaveHistory->perPage(),
                'total' => $leaveHistory->total(),
            ],
        ], 200);
    }

    /**
     * GET /api/leave-history/{id}
     * Student only - retrieve details of a specific leave request
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        $leaveRequest = LeaveRequest::with(['leaveType', 'reviewer', 'comments', 'attachments'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Leave request details retrieved successfully.',
            'data' => $leaveRequest,
        ], 200);
    }
}
