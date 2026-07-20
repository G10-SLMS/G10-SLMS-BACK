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

    protected function prepareForValidation(): void
    {
        if ($this->has('id_card') && $this->id_card !== null) {
            $this->merge(['id_card' => (string) $this->id_card]);
        }
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'avatar_id' => ['sometimes', 'nullable', 'integer', Rule::exists('avatars', 'id')->where('is_default', true)],

            // Applies to all roles
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],

            // Student-only
            'id_card' => ['sometimes', 'nullable', 'string', 'max:50'],
            'class' => ['sometimes', 'nullable', 'string', 'max:100'],
            'generation' => ['sometimes', 'nullable', 'string', 'max:50'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar_id.exists' => 'Please choose a valid avatar.',
        ];
    }
}
