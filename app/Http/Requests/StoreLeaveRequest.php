<?php

namespace App\Http\Requests;

use App\Models\LeaveType;
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
            'supporting_document' => [
                $this->selectedLeaveTypeRequiresAttachment() ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,docx',
                'max:5120',
            ],
        ];
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

            'supporting_document.required' => 'This leave type requires a supporting document.',
            'supporting_document.file' => 'Supporting document must be a valid file.',
            'supporting_document.mimes' => 'Supporting document must be a PDF, DOCX, JPG, or PNG file.',
            'supporting_document.max' => 'Supporting document must not exceed 5MB.',
        ];
    }
}
