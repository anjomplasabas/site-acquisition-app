<?php

namespace App\Http\Controllers;

use App\Enums\ProjectActionPermission;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\SitePlanningAssignment;
use App\Models\User;
use App\Support\ProjectStageFieldDefinitions;
use App\Support\StageDocumentRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->with(['pipeline.siteProfile.address', 'pipeline.projectManager', 'stages', 'tower'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pipeline', fn ($pq) => $pq->where('name', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only('search', 'status'),
            'statuses' => array_column(ProjectStatus::cases(), 'value'),
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'pipeline.siteProfile.address',
            'pipeline.networkSiteAssignment:id,pipeline_id',
            'tower',
            'stages' => fn ($q) => $q->orderBy('order'),
            'stages.completedBy',
            'stages.documents',
            'stages.isps.networkFeasibility.tasks.documents',
            'stages.stageTasks.documents',
        ]);

        // The SitePlanningAssignment matching THIS project's own tower — pipeline's
        // sitePlanningAssignment() accessor only ever resolves the untowered default, which is
        // null once a site has fanned out into multiple towers.
        $sitePlanningAssignment = SitePlanningAssignment::query()
            ->where('pipeline_id', $project->pipeline_id)
            ->where('tower_id', $project->tower_id)
            ->with(['sitePlanningTasks.documents', 'completedBy:id,full_name', 'approvedBy:id,full_name'])
            ->first();

        $user = $request->user();
        $documentRequirements = app(StageDocumentRequirements::class);

        // BOQ/PR files are Manager-level-and-up only — an SAQ Specialist can view the
        // Project page but must not receive these documents (or their download URLs) in the
        // payload at all, not just have the UI hide them.
        if (! $user->role?->hasActionPermission(ProjectActionPermission::ManageStageFields->value)) {
            foreach ($project->stages as $stage) {
                $stage->setRelation('documents', $stage->documents->reject(
                    fn ($document) => in_array($document->category, ['BOQ', 'PR'], true)
                )->values());
            }
        }

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'sitePlanningAssignment' => $sitePlanningAssignment,
            'networkSiteAssignmentId' => $project->pipeline->networkSiteAssignment?->id,
            'projectStatuses' => array_column(ProjectStatus::cases(), 'value'),
            'stages' => array_map(fn (ProjectStage $stage): array => [
                'value' => $stage->value,
                'label' => $stage->label(),
            ], ProjectStage::ordered()),
            'stageFieldDefinitions' => collect(ProjectStage::ordered())
                ->mapWithKeys(fn (ProjectStage $stage) => [$stage->value => array_map(
                    fn (array $field): array => $field + ['editable' => ProjectStageFieldDefinitions::isEditableBy($user, $field)],
                    ProjectStageFieldDefinitions::forStage($stage),
                )])
                ->all(),
            // Every active category, required or not — what a user may pick from when uploading.
            'stageDocumentRequirements' => collect(ProjectStage::ordered())
                ->mapWithKeys(fn (ProjectStage $stage) => [$stage->value => $documentRequirements->activeForProjectStage($stage)->pluck('name')])
                ->all(),
            // Required-only subset — what actually blocks completing the stage (StagePanel.vue's
            // client-side "Complete" button gate mirrors the server-side check in
            // ProjectStageController::complete/StageDocumentRequirements::ensureProjectStageSatisfied).
            'requiredStageDocumentRequirements' => collect(ProjectStage::ordered())
                ->mapWithKeys(fn (ProjectStage $stage) => [$stage->value => $documentRequirements->requiredForProjectStage($stage)->pluck('name')])
                ->all(),
            // Used by Execution's "PM In-charge" dropdown: active Surveyors only, plus whoever
            // is already saved there so a since-deactivated PM still renders instead of going blank.
            'users' => User::query()
                ->where(fn ($query) => $query->activeSurveyors()
                    ->orWhereIn('id', array_filter([$project->stages->firstWhere('stage', ProjectStage::Execution)?->field_values['pm_in_charge'] ?? null])))
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
            'can' => [
                'updateStatus' => $user->can('updateStatus', $project),
                'manageStage' => collect(ProjectStage::cases())
                    ->mapWithKeys(fn (ProjectStage $stage) => [$stage->value => $user->can('manageStage', [$project, $stage])])
                    ->all(),
                'completeStage' => collect(ProjectStage::cases())
                    ->mapWithKeys(fn (ProjectStage $stage) => [$stage->value => $user->can('completeStage', [$project, $stage])])
                    ->all(),
                'reopenStage' => collect(ProjectStage::cases())
                    ->mapWithKeys(fn (ProjectStage $stage) => [$stage->value => $user->can('reopenStage', [$project, $stage])])
                    ->all(),
                // Lets Monitoring's Network Deployment Status checklist be actioned directly from
                // Project Management (in addition to the Network module) — gated by the same
                // NetworkPolicy ability the Network module itself uses, not by manageStage, since
                // it mutates a NetworkFeasibilityTask, not a Project Management resource.
                'manageNetworkFeasibility' => $project->pipeline->networkSiteAssignment
                    ? $user->can('manageFeasibility', $project->pipeline->networkSiteAssignment)
                    : false,
            ],
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('updateStatus', $project);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_column(ProjectStatus::cases(), 'value'))],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        if (($validated['status'] ?? null) === ProjectStatus::Completed->value) {
            $progress = $project->progress;

            if ($progress['total'] === 0 || $progress['done'] < $progress['total']) {
                throw ValidationException::withMessages([
                    'status' => 'All stages must be completed before marking the project Completed.',
                ]);
            }
        }

        $project->update($validated);

        return back()->with('success', 'Project updated.');
    }
}
