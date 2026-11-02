<?php

namespace App\Http\Requests;

use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Models\ApprovalConfig;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateApprovalStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $roleId = $this->integer('role_id');
            $role = Role::find($roleId);

            if ($role === null) {
                return;
            }

            if (! $this->roleCanApprove($role)) {
                $validator->errors()->add(
                    'role_id',
                    'The selected role does not have approval permissions and cannot be assigned as an approver.'
                );

                return;
            }

            $step = (int) $this->route('step');
            $isConsecutiveDuplicate = ApprovalConfig::where('is_active', true)
                ->whereIn('step', [$step - 1, $step + 1])
                ->where('role_id', $roleId)
                ->exists();

            if ($isConsecutiveDuplicate) {
                $validator->errors()->add(
                    'role_id',
                    'The same role cannot be assigned to two consecutive approval steps.'
                );
            }
        });
    }

    private function roleCanApprove(Role $role): bool
    {
        return $role->hasActionPermission(PipelineActionPermission::DecideApproval->value)
            && $role->hasPermissionFor(PipelineStatus::Approval);
    }
}
