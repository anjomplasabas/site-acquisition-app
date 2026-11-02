<?php

namespace App\Http\Requests;

use App\Enums\PipelineStatus;
use App\Models\StageDocumentRequirement;
use App\Support\DocumentUploader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetCommercialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $validCategories = StageDocumentRequirement::active()
            ->where('module', 'pipeline')
            ->where('stage', PipelineStatus::Commercials->value)
            ->pluck('name')
            ->toArray();

        return [
            'remarks' => ['nullable', 'string', 'max:5000'],
            'document_category' => ['nullable', 'string', Rule::in($validCategories)],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ];
    }
}
