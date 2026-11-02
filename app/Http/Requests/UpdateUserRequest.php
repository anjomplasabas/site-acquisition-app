<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$this->route('user')->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'status' => ['required', 'in:ACTIVE,INACTIVE'],
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
