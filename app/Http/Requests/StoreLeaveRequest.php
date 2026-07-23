<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_with:start_time', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'attachment' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'Please select a leave type.',
            'leave_type_id.exists' => 'Selected leave type does not exist.',

            'reason.required' => 'Please provide a reason for your leave request.',
            'reason.max' => 'Reason must not exceed 500 characters.',

            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.after_or_equal' => 'Start date must be today or a future date.',

            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',

            // Time : corresponsding messages
            // Start time
            'start_time.required_with' => 'Start time is required when an end time is provided.',
            'start_time.date_format' => 'Start time must be in the format HH:MM.',
            
            // End time 
            'end_time.required_with' => 'End time is required when a start time is provided.',
            'end_time.date_format' => 'End time must be in the format HH:MM.',
            'end_time.after' => 'End time must be later than the start time.',
        ];
    }

    public function withValidator(Validator $validator): void{
        $validator->after(function ($validator) {
            if (
                $this->start_date === $this->end_date &&
                $this->start_time &&
                $this->end_time &&
                strtotime($this->end_time) <= strtotime($this->start_time)
            ) {
                $validator->errors()->add(
                    'end_time',
                    'End time must be later than the start time for a same-day leave.'
                );
            }
        });
    }
}