<?php

namespace App\Http\Requests;

use App\Models\StageDocumentRequirement;
use App\Support\DocumentUploader;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $validCategories = StageDocumentRequirement::active()->pluck('name')->unique()->values()->toArray();

        return [
            'floors_surveyed' => ['nullable', 'integer', 'min:1'],
            'actual_unit_count' => ['nullable', 'integer', 'min:0'],
            'has_roof_access' => ['nullable', 'boolean'],
            'equipment_notes' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'building_network_system' => ['nullable', 'string', 'max:255'],
            'indoor_outlet_capacity' => ['nullable', 'string', 'max:255'],
            'isp_count_per_unit' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'telco_room_location' => ['nullable', 'string', 'max:255'],
            'electrical_room_location' => ['nullable', 'string', 'max:255'],
            'lobby_booth_allowed' => ['nullable', 'boolean'],
            'lobby_standee_allowed' => ['nullable', 'boolean'],
            'lobby_flyers_stand_allowed' => ['nullable', 'boolean'],
            'roaming_sales_rep_allowed' => ['nullable', 'boolean'],
            'telco_room_fee' => ['nullable', 'numeric', 'min:0', 'max:2147483647'],
            'marketing_paraphernalia' => ['nullable', 'string', 'max:2000'],
            'brochure_posting_location' => ['nullable', 'string', 'max:2000'],
            'providers' => ['nullable', 'array'],
            'providers.*.provider_name' => ['required_with:providers', 'string', 'max:255'],
            'document_category' => ['nullable', 'string', Rule::in($validCategories)],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'providers.*.provider_name.required_with' => 'Provider Name is required.',
            // The defaults expose the raw mimes list and kilobyte count.
            'files.*.mimes' => 'Invalid file format. Please upload a file in one of the supported formats: JPG, JPEG, PNG, GIF, WEBP, PDF, DOC(X), XLS(X), PPT(X), TXT, CSV, ZIP.',
            'files.*.max' => 'Each file must be 100MB or smaller.',
        ];
    }

    /**
     * On-screen labels for validation messages — the defaults expose snake_case field names
     *.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'providers.*.provider_name' => 'Provider Name',
            'floors_surveyed' => 'Floors Surveyed',
            'actual_unit_count' => 'Actual Unit Count',
            'equipment_notes' => 'Equipment Placement Notes',
            'building_network_system' => 'Building Network System',
            'indoor_outlet_capacity' => 'No. of Distribution Box Needed',
            'isp_count_per_unit' => 'ISP Count Per Unit',
            'telco_room_location' => 'Telco Room Location',
            'electrical_room_location' => 'Electrical Room Location',
            'telco_room_fee' => 'Telco Room Fee',
            'marketing_paraphernalia' => 'Marketing Paraphernalia',
            'brochure_posting_location' => 'Brochure Posting Location',
        ];
    }
}
