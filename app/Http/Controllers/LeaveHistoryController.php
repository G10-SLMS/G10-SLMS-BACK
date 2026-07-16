<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveHistoryController extends Controller
{
    /**
     * GET /api/leave-history?search=
     * Student only - retrieve their own leave history with optional search
     *
     * Searchable by:
     * - Leave request ID (exact or partial match on ID)
     * - Leave type name (partial match on leave type name)
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = LeaveRequest::with(['leaveType', 'reviewer'])
            ->where('user_id', $request->user()->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                // Search by leave request ID (if search is numeric)
                if (is_numeric($search)) {
                    $q->orWhere('id', $search);
                }

                // Search by leave type name via relationship
                $q->orWhereHas('leaveType', function ($leaveTypeQuery) use ($search) {
                    $leaveTypeQuery->where('name', 'LIKE', '%' . $search . '%');
                });
            });
        }

        $leaveHistory = $query->latest()->paginate(10);

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
