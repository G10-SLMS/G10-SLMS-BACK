<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['sometimes', 'nullable', Rule::in(['admin', 'educator', 'student'])],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'educator_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'student_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'class_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'generation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'province' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'educator_id.exists' => 'The selected educator does not exist.',
        ];
    }
}
