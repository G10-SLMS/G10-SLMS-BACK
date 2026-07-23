<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'duration_type' => ['required', Rule::in(LeaveRequest::DURATION_TYPES)],

            'start_time' => [
                'nullable',
                'date_format:H:i',
                Rule::requiredIf(fn () => $this->input('duration_type') === 'hourly'),
            ],

            'end_time' => [
                'nullable',
                'date_format:H:i',
                Rule::requiredIf(fn () => $this->input('duration_type') === 'hourly'),
                function ($attribute, $value, $fail) {
                    $this->validateHourlyTimeRange($value, $fail);
                },
            ],

            'reason' => ['required', 'string', 'min:5', 'max:500'],

            'supporting_document' => [
                $this->selectedLeaveTypeRequiresAttachment() ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,docx',
                'max:5120',
            ],
        ];
    }

    protected function validateHourlyTimeRange($endTime, $fail): void
    {
        if ($this->input('duration_type') !== 'hourly') {
            return;
        }

        $startTime = $this->input('start_time');

        if (!$startTime || !$endTime) {
            return;
        }

        $hours = LeaveRequest::calculateHoursFromTimes($startTime, $endTime);

        if ($hours <= 0) {
            $fail('End time must be after start time.');
            return;
        }

        if (!LeaveRequest::isValidHourlyDuration($hours)) {
            $fail(sprintf(
                'Duration must be between %s and %s hours, in increments of %s.',
                LeaveRequest::MIN_HOURLY_DURATION,
                LeaveRequest::MAX_HOURLY_DURATION,
                LeaveRequest::HOURLY_DURATION_STEP
            ));
        }
    }

    protected function selectedLeaveTypeRequiresAttachment(): bool
    {
        $leaveTypeId = $this->input('leave_type_id');

        if (!$leaveTypeId) {
            return false;
        }

        return (bool) LeaveType::whereKey($leaveTypeId)->value('requires_attachment');
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'Please select a leave type.',
            'leave_type_id.exists' => 'Selected leave type does not exist.',

            'reason.required' => 'Please provide a reason for your leave request.',
            'reason.min' => 'Reason must be at least 5 characters.',
            'reason.max' => 'Reason must not exceed 500 characters.',

            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.after_or_equal' => 'Start date must be today or a future date.',

            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',

            'start_time.required' => 'Start time is required for hourly leave.',
            'start_time.date_format' => 'Start time must be in the format HH:MM.',

            'end_time.required' => 'End time is required for hourly leave.',
            'end_time.date_format' => 'End time must be in the format HH:MM.',

            'supporting_document.required' => 'Supporting document is required.',
            'supporting_document.mimes' => 'The file must be a PDF, JPG, JPEG, PNG, or DOCX.',
            'supporting_document.max' => 'The file size must not exceed 5 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (
                $this->input('duration_type') === 'hourly' &&
                $this->filled('start_time') &&
                $this->filled('end_time') &&
                strtotime($this->input('end_time')) <= strtotime($this->input('start_time'))
            ) {
                $validator->errors()->add(
                    'end_time',
                    'End time must be later than the start time.'
                );
            }
        });
    }
}