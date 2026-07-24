<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveHistoryController extends Controller
{
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

        $leaveHistory = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Leave history retrieved successfully.',
            'data' => $leaveHistory->items(),
            'meta' => [
                'current_page' => $leaveHistory->currentPage(),
                'last_page' => $leaveHistory->lastPage(),
                'per_page' => $leaveHistory->perPage(),
                'total' => $leaveHistory->total(),
                'from' => $leaveHistory->firstItem(),
                'to' => $leaveHistory->lastItem(),
                'path' => $leaveHistory->path(),
                'first_page_url' => $leaveHistory->url(1),
                'last_page_url' => $leaveHistory->url($leaveHistory->lastPage()),
                'next_page_url' => $leaveHistory->nextPageUrl(),
                'prev_page_url' => $leaveHistory->previousPageUrl(),
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
