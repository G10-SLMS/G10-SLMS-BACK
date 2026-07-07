<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        $role = $this->user()->role;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['sometimes', 'nullable', 'image', 'max:2048'], // 2MB max

            // Applies to all roles
            'phone' => ['sometimes', 'required', 'string', 'max:20'],

            // Student-only
            'class' => [Rule::requiredIf($role === 'student'), 'nullable', 'string', 'max:100'],
            'generation' => [Rule::requiredIf($role === 'student'), 'nullable', 'string', 'max:50'],
            'province' => [Rule::requiredIf($role === 'student'), 'nullable', 'string', 'max:100'],
            'gender' => [Rule::requiredIf($role === 'student'), 'nullable', Rule::in(['male', 'female'])],
        ];
    }

    public function messages(): array
    {
        return [
            'class.required' => 'The class field is required for students.',
            'generation.required' => 'The generation field is required for students.',
            'province.required' => 'The province field is required for students.',
            'gender.required' => 'The gender field is required for students.',
        ];
    }
}
