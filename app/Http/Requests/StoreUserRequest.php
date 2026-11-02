<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'status' => ['required', Rule::in(['ACTIVE'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'full_name.regex' => 'Full name may only contain letters, spaces, hyphens, apostrophes, and periods.',
        ];
    }
}
