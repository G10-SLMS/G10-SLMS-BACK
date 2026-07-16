<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     * Get all leave type
     */
    public function index()
    {
        $leaveTypes = LeaveType::all();
        return response()->json([
            'success' => true,
            'message' => 'Leave types are retrieved successfully',
            'data' => $leaveTypes
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     * Create leave types only Admin
     */
    public function store(StoreLeaveTypeRequest $request)
    {
        $leaveType = LeaveType::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Leave type created successfully',
            'data' => $leaveType
        ], 201);
    }

    /**
     * Display the specified resource.
     * Get single leave type (all user: Admin, Trainer, and Student)
     */
    public function show(string $id)
    {
        $leaveType = LeaveType::find($id);
        
        // Return HTTP 404 if Leave type not found
        if (!$leaveType) {
            return response()->json([
                'success' => false,
                'message' => 'Leave type not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave type is retrieved successfully',
            'data' => $leaveType
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     * Update leave type (Admin only)
     */
    public function update(UpdateLeaveTypeRequest $request, string $id)
    {
        // Returns HTTP 404 Not Found if the leave types does not exist.
        $leaveType = LeaveType::find($id);
        if (!$leaveType) {
            return response()->json([
                'success' => false,
                'message' => 'Leave type not found.'
            ], 404);
        }

        $leaveType->update($request->validated());

        // Return HTTP 200 OK
        return response()->json([
            'success' => true,
            'message' => 'Leave type updated successfully',
            'data' => $leaveType
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     * Delete leave type (Admin only)
     */
    public function destroy(string $id)
    {
        $leaveType = LeaveType::find($id);

        // Return HTTP 404 Not Found
        if (!$leaveType) {
            return response()->json([
                'success' => false,
                'message' => 'Leave type not found'
            ], 404);
        }
        // Check if Leave type is being used
        if ($leaveType->leaveRequests()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete leave type that is being used in leave requests.'
            ], 409);
        }

        $leaveType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leave type deleted successfully'
        ], 200);
    }
}
