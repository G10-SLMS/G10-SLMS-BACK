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

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['class_name', 'generation', 'student_id'] as $field) {
            if ($this->has($field)) {
                $merge[$field] = $this->normalize($this->input($field));
            }
        }
        $this->merge($merge);
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $value));

        return $normalized === '' ? null : $normalized;
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
