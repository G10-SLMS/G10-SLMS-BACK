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
        $leaveHistory = LeaveRequest::with(['leaveType', 'approver'])
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
}
