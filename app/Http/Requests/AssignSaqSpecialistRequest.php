<?php

namespace App\Http\Requests;

use App\Enums\PipelineStatus;
use App\Models\Pipeline;
use Illuminate\Foundation\Http\FormRequest;

class AssignSaqSpecialistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        /** @var Pipeline|null $pipeline */
        $pipeline = $this->route('pipeline');
        $lateReassignment = $pipeline !== null
            && ! in_array($pipeline->status, [PipelineStatus::Prospect, PipelineStatus::Prospecting], true);

        return [
            'assigned_saq_id' => ['required', 'integer', 'exists:users,id'],
            // Reassigning after Prospecting is the Super Admin resigned-staff flow —
            // the reason is mandatory so it lands in the pipeline's remark history.
            'remarks' => [$lateReassignment ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'remarks.required' => 'Remarks are required when reassigning the SAQ Specialist after Prospecting.',
        ];
    }
}
