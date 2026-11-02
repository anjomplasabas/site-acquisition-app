<?php

namespace App\Http\Requests;

use App\Http\Controllers\SitePlanningController;
use App\Models\StageDocumentRequirement;
use App\Support\DocumentUploader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        $validCategories = StageDocumentRequirement::active()->pluck('name')->unique()->values()->toArray();

        return [
            'surveyor_id' => ['required', 'integer', 'exists:users,id'],
            'survey_date' => ['required', 'date', 'after_or_equal:today'],
            'design_plan_type' => ['required', 'string', Rule::in(SitePlanningController::DESIGN_PLAN_TYPES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'document_category' => ['nullable', 'string', Rule::in($validCategories)],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ];
    }
}
