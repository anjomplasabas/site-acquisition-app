<?php

namespace App\Http\Requests;

use App\Enums\NetworkActionPermission;
use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\ProjectActionPermission;
use App\Enums\ProjectStage;
use App\Enums\SidebarModule;
use App\Enums\SitePlanningActionPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}\s\-_.]+$/u', Rule::unique('roles', 'name')->ignore($this->route('role'))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_column(PipelineStatus::cases(), 'value'))],
            'module_permissions' => ['nullable', 'array'],
            'module_permissions.*' => ['string', Rule::in(array_column(SidebarModule::cases(), 'value'))],
            'action_permissions' => ['nullable', 'array'],
            'action_permissions.*' => ['string', Rule::in([
                ...array_column(PipelineActionPermission::cases(), 'value'),
                ...array_column(ProjectActionPermission::cases(), 'value'),
                ...array_column(NetworkActionPermission::cases(), 'value'),
                ...array_column(SitePlanningActionPermission::cases(), 'value'),
            ])],
            'project_stage_permissions' => ['nullable', 'array'],
            'project_stage_permissions.*' => ['string', Rule::in(array_column(ProjectStage::cases(), 'value'))],
        ];
    }
}
