<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['admin', 'trainer', 'student'])],
            'trainer_id' => ['nullable', 'exists:users,id'],
            'id_card' => ['nullable', 'integer', 'min:0'],

            // Applies to all roles
            'phone' => ['required', 'string', 'max:20'],

            // Student-only
            'class' => ['required_if:role,student', 'nullable', 'string', 'max:100'],
            'generation' => ['required_if:role,student', 'nullable', 'string', 'max:50'],
            'province' => ['required_if:role,student', 'nullable', 'string', 'max:100'],
            'gender' => ['required_if:role,student', 'nullable', Rule::in(['male', 'female'])],
        ];
    }

    public function messages(): array
    {
        return [
            'trainer_id.exists' => 'The selected trainer does not exist.',
            'class.required_if' => 'The class field is required for students.',
            'generation.required_if' => 'The generation field is required for students.',
            'province.required_if' => 'The province field is required for students.',
            'gender.required_if' => 'The gender field is required for students.',
        ];
    }
}
