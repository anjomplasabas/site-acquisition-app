<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Support\ProjectStageFieldDefinitions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectStageRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $stage = $this->route('stage');

        $rules = [];

        foreach (ProjectStageFieldDefinitions::forStage($stage->stage) as $field) {
            $key = "field_values.{$field['key']}";

            // document_status fields aren't stored in field_values at all — their status lives on
            // the project_stage_documents pivot (see ProjectStageController::updateDocumentStatus).
            if ($field['type'] === 'document_status') {
                continue;
            }

            // document_upload fields have no value of their own — they're just a file list keyed
            // by document_category (project_stage_documents), managed entirely via the stage's
            // document upload/delete routes.
            if ($field['type'] === 'document_upload') {
                continue;
            }

            // isp_task_list is a real ProjectStageIsp/IspTask sub-resource, not a field_values
            // entry — see ProjectStageIspController.
            if ($field['type'] === 'isp_task_list') {
                continue;
            }

            // computed fields (e.g. Execution's Duration) are derived server-side and never
            // accepted from the client — see ProjectStageController::computeExecutionDuration.
            if ($field['type'] === 'computed') {
                continue;
            }

            // stage_task_list is a real StageTask sub-resource, not a field_values entry — see
            // StageTaskController.
            if ($field['type'] === 'stage_task_list') {
                continue;
            }

            $rules[$key] = match ($field['type']) {
                'select' => ['sometimes', 'nullable', 'string', Rule::in($field['options'] ?? [])],
                'date' => ['sometimes', 'nullable', 'date'],
                'user_select' => ['sometimes', 'nullable', 'integer', ($field['surveyors_only'] ?? false)
                    ? Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('status', 'ACTIVE')
                        ->whereIn('role_id', Role::query()->where('name', 'Surveyor')->select('id')))
                    : 'exists:users,id'],
                default => ['sometimes', 'nullable', 'string', 'max:255'],
            };

            // A date field can require itself to fall on/after another field's date (e.g.
            // Mobilization Schedule vs. Kick-off Meeting — falls back to the stage's
            // already-saved value for the target field when this request doesn't also update it.
            if ($field['type'] === 'date' && ($targetKey = $field['after_or_equal_field'] ?? null) !== null) {
                $targetValue = $this->input("field_values.{$targetKey}") ?? $stage->field_values[$targetKey] ?? null;

                if ($targetValue !== null) {
                    $rules[$key][] = "after_or_equal:{$targetValue}";
                }
            }

            if ($field['notes'] ?? false) {
                // A field can require its notes/remarks only when its own value matches one of
                // a configured set (e.g. Closing's RFCO/Acceptance Status → 'On-hold') —
                // every other 'notes' field (e.g. Execution's Testing) stays always-optional.
                $triggerValues = $field['notes_required_when'] ?? [];
                $isRequired = $triggerValues !== [] && in_array($this->input($key), $triggerValues, true);

                $rules["field_values.{$field['key']}_notes"] = $isRequired
                    ? ['required', 'string', 'max:2000']
                    : ['sometimes', 'nullable', 'string', 'max:2000'];
            }
        }

        $rules['field_values'] = ['sometimes', 'array'];

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'field_values.rfco_status_notes.required' => 'Remarks are required when RFCO Status is On Hold.',
            'field_values.acceptance_status_notes.required' => 'Remarks are required when Acceptance Status is On Hold.',
            'field_values.pm_in_charge.exists' => 'PM In-charge must be an active user with the Surveyor role.',
            'field_values.mobilization_schedule.after_or_equal' => 'Mobilization Schedule must be on or after the Kick-Off Meeting date.',
        ];
    }
}
