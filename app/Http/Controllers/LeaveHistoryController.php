<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveHistoryController extends Controller
{
    /**
     * GET /api/leave-history?search=&leave_type=&status=&start_date=&end_date=
     * Student only - retrieve their own leave history with optional search and filters
     *
     * Searchable by:
     * - Leave request ID (exact or partial match on ID)
     * - Leave type name (partial match on leave type name)
     *
     * Filterable by:
     * - leave_type: Filter by leave type ID
     * - status: Filter by status (pending, approved, rejected, cancelled)
     * - start_date & end_date: Filter by date range (inclusive)
     */
    public function index(Request $request)
    {
        $query = LeaveRequest::with(['leaveType', 'reviewer'])
            ->where('user_id', $request->user()->id);

        // Search filter
        if ($search = $request->query('search')) {
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

        // Filter by leave type
        if ($leaveType = $request->query('leave_type')) {
            $query->where('leave_type_id', $leaveType);
        }

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter by date range (inclusive)
        if ($startDate = $request->query('start_date')) {
            $query->whereDate('start_date', '>=', $startDate);
        }

        if ($endDate = $request->query('end_date')) {
            $query->whereDate('end_date', '<=', $endDate);
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
