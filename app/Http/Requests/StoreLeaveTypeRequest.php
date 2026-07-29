<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:leave_types,name',
            'code' => 'required|string|max:255|unique:leave_types,code',
            'description' => 'nullable|string|max:1000',
            'max_days_per_year' => 'required|integer|min:0',
            'requires_attachment' => 'required|boolean',
            'is_active' => 'required|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The leave type name is required.',
            'name.max' => 'The leave type name may not be greater than 255 characters.',
            'name.unique' => 'The leave type name has already been taken.',
            'code.required' => 'The leave type code is required.',
            'code.max' => 'The leave type code may not be greater than 255 characters.',
            'code.unique' => 'The leave type code has already been taken.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            'max_days_per_year.required' => 'The maximum days per year is required.',
            'max_days_per_year.integer' => 'The maximum days per year must be an integer.',
            'max_days_per_year.min' => 'The maximum days per year must be at least 0.',
            'requires_attachment.required' => 'The requires attachment field is required.',
            'requires_attachment.boolean' => 'The requires attachment field must be true or false.',
            'is_active.required' => 'The is active field is required.',
            'is_active.boolean' => 'The is active field must be true or false.',
        ];
    }
}
