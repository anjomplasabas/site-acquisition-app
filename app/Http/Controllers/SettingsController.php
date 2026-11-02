<?php

namespace App\Http\Controllers;

use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\ProjectStage;
use App\Http\Requests\UpdateApprovalStepRequest;
use App\Models\ApprovalConfig;
use App\Models\Role;
use App\Models\StageDocumentRequirement;
use App\Support\WorkflowSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function approvals(WorkflowSettings $workflowSettings): Response
    {
        $activeConfigs = ApprovalConfig::with('role:id,name')
            ->where('is_active', true)
            ->orderBy('step')
            ->get()
            ->keyBy('step');

        return Inertia::render('Settings/Approvals', [
            'activeConfigs' => $activeConfigs->values(),
            'roles' => Role::select('id', 'name')
                ->whereHas('actionPermissions', fn ($q) => $q->where('permission', PipelineActionPermission::DecideApproval->value))
                ->whereHas('permissions', fn ($q) => $q->where('pipeline_status', PipelineStatus::Approval->value))
                ->orderBy('name')
                ->get(),
            'stageDocumentRequirements' => StageDocumentRequirement::orderBy('module')->orderBy('stage')->orderBy('sort_order')->orderBy('name')->get(),
            'pipelineStages' => array_column(PipelineStatus::cases(), 'value'),
            'projectStages' => array_column(ProjectStage::cases(), 'value'),
            'alarmThresholds' => $workflowSettings->alarmThresholds(),
        ]);
    }

    public function updateApprovalStep(UpdateApprovalStepRequest $request, int $step): RedirectResponse
    {
        ApprovalConfig::where('step', $step)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        ApprovalConfig::create([
            'step' => $step,
            'role_id' => $request->validated()['role_id'],
            'is_active' => true,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return redirect()->route('settings.approvals')
            ->with('success', "Step {$step} approver updated successfully.");
    }

    public function removeApprovalStep(int $step): RedirectResponse
    {
        $activeCount = ApprovalConfig::where('is_active', true)->count();

        abort_if($activeCount <= 1, 422, 'At least one approval step is required.');

        ApprovalConfig::where('step', $step)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        ApprovalConfig::where('is_active', true)
            ->where('step', '>', $step)
            ->orderBy('step')
            ->get()
            ->each(fn (ApprovalConfig $config) => $config->update(['step' => $config->step - 1]));

        return redirect()->route('settings.approvals')
            ->with('success', "Step {$step} has been deactivated.");
    }

    public function updateAlarmThresholds(Request $request, WorkflowSettings $workflowSettings): RedirectResponse
    {
        $validated = $request->validate([
            'yellow_days' => ['required', 'integer', 'min:1', 'max:365'],
            'red_days' => ['required', 'integer', 'min:1', 'max:365', 'gte:yellow_days'],
        ]);

        $workflowSettings->updateAlarmThresholds($validated['yellow_days'], $validated['red_days']);

        return redirect()->route('settings.approvals')
            ->with('success', 'Site alarm thresholds updated successfully.');
    }

    public function storeStageDocumentRequirement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', Rule::in(['pipeline', 'project'])],
            'stage' => ['required', 'string', function ($attribute, $value, $fail) use ($request) {
                $this->validateStageForModule($request->string('module')->value(), $value, $fail);
            }],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('stage_document_requirements')->where(fn ($query) => $query
                    ->where('module', $request->input('module'))
                    ->where('stage', $request->input('stage'))),
            ],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.unique' => 'A document requirement with the same stage and name already exists.',
        ]);

        StageDocumentRequirement::create([
            'module' => $validated['module'],
            'stage' => $validated['stage'],
            'name' => $validated['name'],
            'is_required' => $validated['is_required'] ?? true,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('settings.approvals')
            ->with('success', 'Stage document requirement saved successfully.');
    }

    public function updateStageDocumentRequirement(Request $request, StageDocumentRequirement $stageDocumentRequirement): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', Rule::in(['pipeline', 'project'])],
            'stage' => ['required', 'string', function ($attribute, $value, $fail) use ($request) {
                $this->validateStageForModule($request->string('module')->value(), $value, $fail);
            }],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('stage_document_requirements')->where(fn ($query) => $query
                    ->where('module', $request->input('module'))
                    ->where('stage', $request->input('stage')))
                    ->ignore($stageDocumentRequirement->id),
            ],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.unique' => 'A document requirement with the same stage and name already exists.',
        ]);

        $stageDocumentRequirement->update([
            'module' => $validated['module'],
            'stage' => $validated['stage'],
            'name' => $validated['name'],
            'is_required' => $validated['is_required'] ?? false,
            'is_active' => $validated['is_active'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('settings.approvals')
            ->with('success', 'Stage document requirement updated successfully.');
    }

    private function validateStageForModule(string $module, string $stage, \Closure $fail): void
    {
        $validStages = $module === 'project'
            ? array_column(ProjectStage::cases(), 'value')
            : array_column(PipelineStatus::cases(), 'value');

        if (! in_array($stage, $validStages, true)) {
            $fail('The selected stage is not valid for the chosen module.');
        }
    }

    public function destroyStageDocumentRequirement(StageDocumentRequirement $stageDocumentRequirement): RedirectResponse
    {
        $stageDocumentRequirement->update(['is_active' => false]);

        return redirect()->route('settings.approvals')
            ->with('success', 'Stage document requirement deactivated.');
    }
}
