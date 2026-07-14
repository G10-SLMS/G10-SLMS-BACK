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
     */
    public function index()
    {
        $leaveTypes = LeaveType::all();
        return response()->json([
            'success' => true,
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
        $leaveType = LeaveType::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $leaveType
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
