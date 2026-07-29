<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select an Excel file to upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.mimes' => 'Only .xlsx, .xls, and .csv files are supported.',
            'file.max' => 'The file may not be greater than 5 MB.',
        ];
    }
}
