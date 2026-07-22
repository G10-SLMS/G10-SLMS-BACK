<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Note: Authorization is handled in the controller instead
     * because route model binding doesn't work in FormRequest::authorize()
     */
    public function authorize(): bool
    {
        // Return true here - actual authorization is in the controller
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $isTrainerOrAdmin = $user && in_array($user->role, ['trainer', 'admin']);
        
        $rules = [
            'leave_type_id' => ['sometimes', 'required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'reason' => ['sometimes', 'required', 'string', 'max:500'],
        ];
        
        // Allow status and review_note only for trainer/admin
        if ($isTrainerOrAdmin) {
            $rules['status'] = ['sometimes', 'required', 'in:approved,rejected'];
            $rules['review_note'] = ['sometimes', 'required', 'string', 'min:5', 'max:500'];
        } else {
            // Students can only cancel their own pending requests
            $rules['status'] = ['sometimes', 'required', 'in:cancelled'];
        }
        
        return $rules;
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
        ];
        
        $user = $this->user();
        if ($user && in_array($user->role, ['trainer', 'admin'])) {
            $messages['status.required'] = 'Please select a status (approved or rejected).';
            $messages['status.in'] = 'Status must be either approved or rejected.';
            $messages['review_note.required'] = 'Please provide a review note.';
            $messages['review_note.min'] = 'Review note must be at least 5 characters.';
            $messages['review_note.max'] = 'Review note must not exceed 500 characters.';
        } else {
            $messages['status.in'] = 'Status must be cancelled.';
        }
        
        return $messages;
    }
}
