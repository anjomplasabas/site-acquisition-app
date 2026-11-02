<?php

namespace App\Http\Requests;

use App\Support\PipelineFileReader;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PreviewPipelineImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'temp_path' => [
                'required',
                'string',
                'regex:/^imports\/temp\/[\w\-]+\.[a-z]+$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Storage::exists($value)) {
                        $fail('The import file is no longer available. Please re-upload.');
                    }
                },
            ],
            'file_extension' => ['required', 'string', 'in:csv,txt,xlsx,xls'],
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string', Rule::in(PipelineFileReader::IMPORTABLE_FIELDS)],
        ];
    }
}
