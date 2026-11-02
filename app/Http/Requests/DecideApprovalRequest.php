<?php

namespace App\Http\Requests;

use App\Enums\ApprovalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([ApprovalStatus::Approved->value, ApprovalStatus::Rejected->value])],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
