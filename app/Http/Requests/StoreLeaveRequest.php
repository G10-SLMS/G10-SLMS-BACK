<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.exists' => 'Selected leave type does not exist.',
            'end_date.after_or_equal' => 'End date cannot be before start date.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
        ];
    }
}