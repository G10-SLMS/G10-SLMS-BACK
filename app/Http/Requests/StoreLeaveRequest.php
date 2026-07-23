<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function ($attribute, $value, $fail) {
                    if ($this->input('duration_type') === 'hourly' && $value !== $this->input('start_date')) {
                        $fail('Hourly leave requests must start and end on the same date.');
                    }
                },
            ],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
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
                LeaveRequest::HOURLY_DURATION_STEP,
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
            'reason.max' => 'Reason must not exceed 500 characters.',

            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.after_or_equal' => 'Start date must be today or a future date.',

            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',

            'duration_type.required' => 'Please select a duration type.',
            'duration_type.in' => 'Duration type must be either full day or hourly.',

            'start_time.required' => 'Please select a start time for your hourly leave.',
            'start_time.date_format' => 'Start time must be a valid time (HH:MM).',

            'end_time.required' => 'Please select an end time for your hourly leave.',
            'end_time.date_format' => 'End time must be a valid time (HH:MM).',

            'supporting_document.required' => 'This leave type requires a supporting document.',
            'supporting_document.file' => 'Supporting document must be a valid file.',
            'supporting_document.mimes' => 'Supporting document must be a PDF, DOCX, JPG, or PNG file.',
            'supporting_document.max' => 'Supporting document must not exceed 5MB.',
        ];
    }
}