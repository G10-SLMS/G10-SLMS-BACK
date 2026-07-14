<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Validator as FacadesValidator;

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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:leave_types,name',
            'code' => 'required|string|max:50|unique:leave_types,code',
            'description' => 'nullable|string|max:1000',
            'max_days_per_year' => 'required|integer|min:0',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $leaveType = LeaveType::create($request->all());

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
            'data' => $leaveType
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     * Update leave type (Admin only)
     */
    public function update(Request $request, string $id)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:leave_types,name,' . $id,
            'code' => 'sometimes|string|max:50|unique:leave_types,code,' . $id,
            'description' => 'nullable|string|max:1000',
            'max_days_per_year' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Validation errors return HTTP 422 Unprocessable Entity.
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Returns HTTP 404 Not Found if the leave types does not exist.
        $leaveType = LeaveType::find($id);
        if (!$leaveType) {
            return response()->json([
                'success' => false,
                'message' => 'Leave type not found.'
            ], 404);
        }

        $leaveType->update($request->all());

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
                'succes' => false,
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
