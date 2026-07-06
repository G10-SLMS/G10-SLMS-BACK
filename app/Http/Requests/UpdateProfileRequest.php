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
        return [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'avatar' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'password' => 'sometimes|string|min:8|confirmed',
        ];
    }
}
