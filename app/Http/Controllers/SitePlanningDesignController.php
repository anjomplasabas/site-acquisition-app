<?php

namespace App\Http\Controllers;

use App\Enums\IspTaskStatus;
use App\Enums\ProjectStage as ProjectStageEnum;
use App\Enums\SiteProfileType;
use App\Models\Document;
use App\Models\Project;
use App\Models\SitePlanningAssignment;
use App\Models\SitePlanningTask;
use App\Models\User;
use App\Support\DocumentUploader;
use App\Support\PipelineActivity;
use App\Support\PipelineNotifier;
use App\Support\SitePlanningTaskSeeder;
use App\Support\SiteSurveyProgression;
use App\Support\StageTaskSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SitePlanningDesignController extends SitePlanningController
{
    public function updateProjectType(Request $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('updateProjectType', $sitePlanningAssignment);

        $validated = $request->validate([
            'type' => ['required', Rule::in(SiteProfileType::selectable())],
            'type_other' => ['nullable', 'required_if:type,'.SiteProfileType::Others->value, 'string', 'max:255'],
        ]);

        $sitePlanningAssignment->pipeline->siteProfile->update([
            'type' => $validated['type'],
            'type_other' => $validated['type'] === SiteProfileType::Others->value ? $validated['type_other'] : null,
        ]);

        app(PipelineActivity::class)->record($sitePlanningAssignment->pipeline, auth()->user(), 'updated the project type');

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Project type updated.');
    }

    public function assignDesignPlan(Request $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('assignDesignPlan', $sitePlanningAssignment);

        $validated = $request->validate([
            'design_plan_assignee_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        abort_unless(
            $this->designPlanEligibleUsers()->whereKey($validated['design_plan_assignee_id'])->exists(),
            422,
            'The selected user must be allowed to work the design plan.'
        );

        if ($sitePlanningAssignment->sitePlanningTasks()->where('status', '!=', IspTaskStatus::Pending->value)->exists()) {
            throw ValidationException::withMessages([
                'design_plan_assignee_id' => 'Cannot reassign the design plan once work has started.',
            ]);
        }

        $assigneeChanged = $sitePlanningAssignment->design_plan_assignee_id !== (int) $validated['design_plan_assignee_id'];

        $sitePlanningAssignment->update(['design_plan_assignee_id' => $validated['design_plan_assignee_id']]);

        if ($assigneeChanged) {
            app(PipelineNotifier::class)->designPlanAssigned($sitePlanningAssignment, User::findOrFail($validated['design_plan_assignee_id']));
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Design plan assigned.');
    }

    public function updateTaskStatus(Request $request, SitePlanningAssignment $sitePlanningAssignment, SitePlanningTask $sitePlanningTask): RedirectResponse
    {
        $this->authorize('manageDesignPlan', $sitePlanningAssignment);
        $this->ensureTaskBelongsToAssignment($sitePlanningAssignment, $sitePlanningTask);

        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', Rule::in(array_column(IspTaskStatus::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $attributes = [];
        if (array_key_exists('status', $validated) && $validated['status'] !== $sitePlanningTask->status->value) {
            $attributes['status'] = $validated['status'];
            $attributes['status_changed_at'] = now();
        }
        if (array_key_exists('notes', $validated)) {
            $attributes['notes'] = $validated['notes'];
        }
        if ($attributes !== []) {
            $sitePlanningTask->update($attributes);
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Task updated.');
    }

    public function completeAllTasks(Request $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('manageDesignPlan', $sitePlanningAssignment);

        $validated = $request->validate([
            'section' => ['sometimes', 'nullable', 'string'],
        ]);

        $query = $sitePlanningAssignment->sitePlanningTasks()
            ->where('status', '!=', IspTaskStatus::Done->value);

        if ($request->has('section')) {
            $validated['section'] === null ? $query->whereNull('section') : $query->where('section', $validated['section']);
        }

        $query->update(['status' => IspTaskStatus::Done->value, 'status_changed_at' => now()]);

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'All tasks marked Done.');
    }

    public function uploadTaskDocument(Request $request, SitePlanningAssignment $sitePlanningAssignment, SitePlanningTask $sitePlanningTask): RedirectResponse
    {
        $this->authorize('manageDesignPlan', $sitePlanningAssignment);
        $this->ensureTaskBelongsToAssignment($sitePlanningAssignment, $sitePlanningTask);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ]);

        foreach ($validated['files'] as $file) {
            $document = $this->documents->store($file, 'site-planning-documents', auth()->user(), $sitePlanningTask->name);
            $sitePlanningTask->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document uploaded.');
    }

    public function deleteTaskDocument(SitePlanningAssignment $sitePlanningAssignment, SitePlanningTask $sitePlanningTask, Document $document): RedirectResponse
    {
        $this->authorize('manageDesignPlan', $sitePlanningAssignment);
        $this->ensureTaskBelongsToAssignment($sitePlanningAssignment, $sitePlanningTask);
        abort_unless($sitePlanningTask->documents()->where('document_id', $document->id)->exists(), 403);

        $sitePlanningTask->documents()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document removed.');
    }

    public function replaceTaskDocument(Request $request, SitePlanningAssignment $sitePlanningAssignment, SitePlanningTask $sitePlanningTask, Document $document): RedirectResponse
    {
        $this->authorize('manageDesignPlan', $sitePlanningAssignment);
        $this->ensureTaskBelongsToAssignment($sitePlanningAssignment, $sitePlanningTask);
        abort_unless($sitePlanningTask->documents()->where('document_id', $document->id)->exists(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'site-planning-documents');

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document replaced.');
    }

    public function updateDesignPlanRemarks(Request $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('manageDesignPlan', $sitePlanningAssignment);

        $validated = $request->validate([
            'design_plan_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $sitePlanningAssignment->update($validated);

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Remarks saved.');
    }

    public function uploadDesignPlanDocument(Request $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('manageDesignPlan', $sitePlanningAssignment);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ]);

        foreach ($validated['files'] as $file) {
            $document = $this->documents->store($file, 'site-planning-documents', auth()->user());
            $sitePlanningAssignment->designPlanDocuments()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document uploaded.');
    }

    public function deleteDesignPlanDocument(SitePlanningAssignment $sitePlanningAssignment, Document $document): RedirectResponse
    {
        $this->authorize('manageDesignPlan', $sitePlanningAssignment);
        abort_unless($sitePlanningAssignment->designPlanDocuments()->where('document_id', $document->id)->exists(), 403);

        $sitePlanningAssignment->designPlanDocuments()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document removed.');
    }

    public function replaceDesignPlanDocument(Request $request, SitePlanningAssignment $sitePlanningAssignment, Document $document): RedirectResponse
    {
        $this->authorize('manageDesignPlan', $sitePlanningAssignment);
        abort_unless($sitePlanningAssignment->designPlanDocuments()->where('document_id', $document->id)->exists(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'site-planning-documents');

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document replaced.');
    }

    /**
     * The Infra Designer's side of finishing the design plan — gated on the checklist being
     * fully done, the Type being set, and at least one Photos & Documents file attached.
     * Doesn't finish the survey outright anymore: it just marks their part done and notifies the
     * Infrastructure Manager, who must approveDesignPlan() before the survey actually completes
     * and Commercials can be reached.
     */
    public function completeDesignPlan(SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('completeDesignPlan', $sitePlanningAssignment);

        abort_if(
            $sitePlanningAssignment->sitePlanningTasks()->doesntExist()
                || $sitePlanningAssignment->sitePlanningTasks()->where('status', '!=', IspTaskStatus::Done->value)->exists()
                || $sitePlanningAssignment->design_plan_type === null,
            422,
            'Complete every Plans/Drawing task and set the Type before completing.'
        );

        abort_if(
            $sitePlanningAssignment->design_plan_completed_at !== null,
            422,
            'Design plan has already been submitted for approval.'
        );

        abort_if(
            $sitePlanningAssignment->designPlanDocuments()->doesntExist(),
            422,
            'Attach at least one file to the Design Plan\'s Photos & Documents section before completing.'
        );

        $sitePlanningAssignment->update([
            'design_plan_completed_at' => now(),
            'design_plan_completed_by_id' => auth()->id(),
            'design_plan_revision_remarks' => null,
        ]);

        app(PipelineNotifier::class)->designPlanPendingApproval($sitePlanningAssignment, auth()->user());

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Design plan submitted. The infrastructure manager has been notified for approval.');
    }

    /**
     * Reclassifies this assignment's design_plan_type after the Plans/Drawing checklist has been
     * generated — the deliberate escape hatch for an early misclassification, gated to the
     * same Infra Manager tier that approves design plans. Rebuilds the Plans/Drawing checklist
     * and, if this tower's project already seeded its Monitoring matrix, that matrix too;
     * both rebuilds carry over status/notes/documents for tasks present in both type's lists
     * (ChecklistReseeder). Approval stamps and pipeline status are deliberately untouched —
     * correcting a classification is not a rejection.
     */
    public function updateDesignPlanType(Request $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('approveDesignPlan', $sitePlanningAssignment);

        $validated = $request->validate([
            'design_plan_type' => ['required', 'string', Rule::in(self::DESIGN_PLAN_TYPES)],
        ]);

        if ($validated['design_plan_type'] === $sitePlanningAssignment->design_plan_type) {
            return redirect()->route('site-planning.show', $sitePlanningAssignment);
        }

        DB::transaction(function () use ($sitePlanningAssignment, $validated): void {
            $sitePlanningAssignment->update(['design_plan_type' => $validated['design_plan_type']]);
            SitePlanningTaskSeeder::reseedFor($sitePlanningAssignment);

            $monitoringStage = Project::query()
                ->where('pipeline_id', $sitePlanningAssignment->pipeline_id)
                ->where('tower_id', $sitePlanningAssignment->tower_id)
                ->first()
                ?->stages()
                ->where('stage', ProjectStageEnum::Monitoring->value)
                ->first();

            if ($monitoringStage !== null && $monitoringStage->stageTasks()->where('field_key', 'infrastructure_deployment_status')->exists()) {
                StageTaskSeeder::reseedFor($monitoringStage, 'infrastructure_deployment_status', $validated['design_plan_type']);
                $monitoringStage->syncProjectStatusFromInfrastructure();
            }
        });

        app(PipelineActivity::class)->record($sitePlanningAssignment->pipeline, auth()->user(), "changed the design plan type to {$validated['design_plan_type']}");

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Design plan type updated — checklists rebuilt for the new type.');
    }

    /**
     * The one action that actually finishes the survey — only reachable once the Infra Designer
     * has completeDesignPlan()'d. Marks the survey completed under the original Surveyor's name
     * (not whoever approves), then defers to the same shared rule the Network module relies on
     * for advancing to Commercials.
     */
    public function approveDesignPlan(SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('approveDesignPlan', $sitePlanningAssignment);

        abort_if(
            $sitePlanningAssignment->design_plan_completed_at === null,
            422,
            'Design plan has not been submitted for approval yet.'
        );

        abort_if(
            $sitePlanningAssignment->design_plan_approved_at !== null,
            422,
            'Design plan has already been approved.'
        );

        $sitePlanningAssignment->update([
            'design_plan_approved_at' => now(),
            'design_plan_approved_by_id' => auth()->id(),
        ]);

        $pipeline = $sitePlanningAssignment->pipeline;
        // This assignment's own tower survey — see persistInspectionData() for why
        // SiteSurveyProgression::ensureSiteSurvey($pipeline) is wrong here on a multi-tower site.
        $survey = $sitePlanningAssignment->siteSurvey;
        $survey->update([
            'survey_completed_at' => now(),
            'survey_completed_by_id' => $survey->surveyor_id,
        ]);

        if (SiteSurveyProgression::advanceFromSiteSurveyWhenReady($pipeline, auth()->user())) {
            return redirect()->route('site-planning.show', $sitePlanningAssignment)
                ->with('success', 'Design plan approved. The account manager has been notified for commercial talks.');
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Design plan approved. Waiting for Network Feasibility to be completed.');
    }

    /**
     * The Infrastructure Manager's alternative to approving: sends a submitted design
     * plan back to its Designer with a reason. Everything already entered (tasks, files, type,
     * assignee) is retained — only the submission marker is cleared so the Designer can revise
     * and resubmit via completeDesignPlan().
     */
    public function requestDesignPlanRevision(Request $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('approveDesignPlan', $sitePlanningAssignment);

        abort_if(
            $sitePlanningAssignment->design_plan_completed_at === null || $sitePlanningAssignment->design_plan_approved_at !== null,
            422,
            'Only a design plan that is pending approval can be sent back for revision.'
        );

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $sitePlanningAssignment->update([
            'design_plan_completed_at' => null,
            'design_plan_completed_by_id' => null,
            'design_plan_revision_remarks' => $validated['remarks'],
        ]);

        app(PipelineNotifier::class)->designPlanRevisionRequested($sitePlanningAssignment, auth()->user(), $validated['remarks']);
        app(PipelineActivity::class)->record($sitePlanningAssignment->pipeline, auth()->user(), 'requested a design plan revision');

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Design plan sent back to the Infra Designer for revision.');
    }
}
