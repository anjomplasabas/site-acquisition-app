<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPipelineImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array'],
            'rows.*.row_number' => ['required', 'integer', 'min:1'],
            'rows.*.data' => ['required', 'array'],
            'rows.*.site_action' => ['nullable', 'string', 'in:create_new,use_existing,skip'],
            'rows.*.site_profile_id' => ['nullable', 'integer', 'exists:site_profiles,id'],
            'rows.*.pipeline_action' => ['nullable', 'string', 'in:create,reattempt,skip'],
        ];
    }
}
