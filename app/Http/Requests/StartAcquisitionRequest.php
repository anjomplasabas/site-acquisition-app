<?php

namespace App\Http\Requests;

use App\Models\StageDocumentRequirement;
use App\Support\DocumentUploader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartAcquisitionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string>|string>
     */
    public function rules(): array
    {
        $validCategories = StageDocumentRequirement::active()->pluck('name')->unique()->values()->toArray();

        return [
            'content' => ['required', 'string', 'max:2000'],
            'document_category' => ['nullable', 'string', Rule::in($validCategories)],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ];
    }
}
