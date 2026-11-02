<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddRemarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
        ];
    }
}
