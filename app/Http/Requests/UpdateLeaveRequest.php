<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $leave = $this->route('leaveRequest');
        return $leave && $this->user()->id === $leave->user_id;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['sometimes', 'exists:leave_types,id'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'reason' => ['sometimes', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'Please select a leave type.',
            'leave_type_id.exists' => 'Selected leave type does not exist.',
            'start_date.required' => 'Start date is required.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'end_date.required' => 'End date is required.',
            'end_date.after_or_equal' => 'End date cannot be before start date.',
            'reason.required' => 'Please provide a reason for your leave request.',
            'reason.min' => 'Reason must be at least 5 characters.',
        ];
    }
}
