<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'class_name' => $this->normalize($this->input('class_name')),
            'generation' => $this->normalize($this->input('generation')),
            'student_id' => $this->normalize($this->input('student_id')),
        ]);
    }

    // Collapses stray internal whitespace so near-identical typos (extra
    // spaces around a dash, double spaces, etc.) don't create a distinct
    // class/generation group in the Student Directory.
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['nullable', Rule::in(['admin', 'educator', 'student'])],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'educator_id' => ['nullable', 'exists:users,id'],
            'student_id' => ['nullable', 'string', 'max:50'],
            'class_name' => ['nullable', 'string', 'max:255'],
            'generation' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'educator_id.exists' => 'The selected educator does not exist.',
        ];
    }
}
