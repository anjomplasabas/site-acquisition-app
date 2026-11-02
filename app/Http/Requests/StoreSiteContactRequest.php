<?php

namespace App\Http\Requests;

use App\Models\SiteContact;
use Illuminate\Foundation\Http\FormRequest;

class StoreSiteContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255', 'regex:/^[\p{L}\s\-\'.]+$/u',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (SiteContact::duplicateExists($value, $this->input('mobile_number'), $this->input('email'))) {
                        $fail('A contact with this name, mobile number, and email already exists.');
                    }
                },
            ],
            'designation' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => 'Name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            'mobile_number.regex' => 'Enter a valid mobile number (e.g. 09171234567 or +639171234567).',
        ];
    }
}
