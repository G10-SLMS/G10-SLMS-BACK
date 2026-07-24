<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveRequest extends FormRequest
{

    public function authorize(): bool
    {
        // Return true here - actual authorization is in the controller
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $isEducatorOrAdmin = $user && in_array($user->role, ['educator', 'admin']);

        $rules = [
            'leave_type_id' => ['sometimes', 'required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => [
                'sometimes',
                'required',
                'date',
                'after_or_equal:start_date',
                function ($attribute, $value, $fail) {
                    $durationType = $this->input('duration_type', $this->route('leaveRequest')?->duration_type);

                    if ($durationType === 'hourly') {
                        $startDate = $this->input('start_date', $this->route('leaveRequest')?->start_date?->toDateString());
                        if ($value !== $startDate) {
                            $fail('Hourly leave requests must start and end on the same date.');
                        }
                    }
                },
            ],
            'reason' => ['sometimes', 'required', 'string', 'max:500'],
            'duration_type' => ['sometimes', 'required', Rule::in(LeaveRequest::DURATION_TYPES)],
            'start_time' => [
                'nullable',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    $durationType = $this->input('duration_type', $this->route('leaveRequest')?->duration_type);
                    if ($durationType === 'hourly' && ($value === null || $value === '')) {
                        $fail('Please select a start time for your hourly leave.');
                    }
                },
            ],
            'end_time' => [
                'nullable',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    $durationType = $this->input('duration_type', $this->route('leaveRequest')?->duration_type);

                    if ($durationType !== 'hourly') {
                        return;
                    }

                    if ($value === null || $value === '') {
                        $fail('Please select an end time for your hourly leave.');
                        return;
                    }

                    $startTime = $this->input('start_time', $this->route('leaveRequest')?->start_time);
                    if (!$startTime) {
                        return;
                    }

                    $minutes = LeaveRequest::calculateMinutesFromTimes($startTime, $value);

                    if ($minutes <= 0) {
                        $fail('End time must be after start time.');
                        return;
                    }

                    if (!LeaveRequest::isValidHourlyDuration($minutes)) {
                        $fail(sprintf(
                            'Leave duration must be between %d minutes and %s hours.',
                            LeaveRequest::MIN_HOURLY_DURATION * 60,
                            LeaveRequest::MAX_HOURLY_DURATION,
                        ));
                    }
                },
            ],
        ];

        // Allow status and review_note only for educator/admin
        if ($isEducatorOrAdmin) {
            $rules['status'] = ['sometimes', 'required', 'in:approved,rejected'];

            if ($this->input('status') === 'rejected') {
                $rules['review_note'] = ['required', 'string', 'min:5', 'max:500'];
            } else {
                $rules['review_note'] = ['nullable', 'string', 'max:500'];
            }
        } else {

            $rules['status'] = ['sometimes', 'required', 'in:cancelled'];
            $rules['supporting_document'] = [
                $this->attachmentStillMissingAfterSave() ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,docx',
                'max:5120',
            ];
            $rules['remove_attachment'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    protected function attachmentStillMissingAfterSave(): bool
    {
        // A new file is being uploaded — requirement satisfied regardless.
        if ($this->hasFile('supporting_document')) {
            return false;
        }

        $leaveTypeId = $this->input('leave_type_id') ?? $this->route('leaveRequest')?->leave_type_id;

        $requiresAttachment = $leaveTypeId
            ? (bool) LeaveType::whereKey($leaveTypeId)->value('requires_attachment')
            : false;

        if (!$requiresAttachment) {
            return false;
        }

        // Explicitly removing the existing document with nothing to replace it.
        if ($this->boolean('remove_attachment')) {
            return true;
        }

        $leaveRequest = $this->route('leaveRequest');

        return !($leaveRequest && $leaveRequest->attachments()->exists());
    }

    public function messages(): array
    {
        $messages = [
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

            'start_time.date_format' => 'Start time must be a valid time (HH:MM).',
            'end_time.date_format' => 'End time must be a valid time (HH:MM).',
        ];

        $user = $this->user();
        if ($user && in_array($user->role, ['educator', 'admin'])) {
            $messages['status.required'] = 'Please select a status (approved or rejected).';
            $messages['status.in'] = 'Status must be either approved or rejected.';
            $messages['review_note.required'] = 'Please provide a review note.';
            $messages['review_note.min'] = 'Review note must be at least 5 characters.';
            $messages['review_note.max'] = 'Review note must not exceed 500 characters.';
        } else {
            $messages['status.in'] = 'Status must be cancelled.';
            $messages['supporting_document.required'] = 'This leave type requires a supporting document.';
            $messages['supporting_document.file'] = 'Supporting document must be a valid file.';
            $messages['supporting_document.mimes'] = 'Supporting document must be a PDF, DOCX, JPG, or PNG file.';
            $messages['supporting_document.max'] = 'Supporting document must not exceed 5MB.';
        }

        return $messages;
    }
}