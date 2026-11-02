<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeclareTowersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'towers' => ['required', 'array', 'min:1'],
            'towers.*.name' => ['required', 'string', 'max:255'],
            'towers.*.total_units' => ['nullable', 'integer', 'min:0'],
            'towers.*.total_floors' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
