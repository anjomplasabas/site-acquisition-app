<?php

namespace App\Http\Controllers;

use App\Enums\IspTaskStatus;
use App\Enums\ProjectStage as ProjectStageEnum;
use App\Http\Requests\UpdateProjectStageRequest;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStageIsp;
use App\Models\SitePlanningAssignment;
use App\Models\StageTask;
use App\Support\DocumentUploader;
use App\Support\NetworkFeasibilityTaskSeeder;
use App\Support\ProjectStageFieldDefinitions;
use App\Support\StageDocumentRequirements;
use App\Support\StageTaskSeeder;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectStageController extends Controller
{
    public function __construct(private readonly DocumentUploader $documents) {}

    public function update(UpdateProjectStageRequest $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        $this->authorize('manageStage', [$project, $stage->stage]);
        abort_if($stage->project_id !== $project->id, 403);
        abort_if($project->current_stage !== $stage->stage, 422, 'This stage is not currently active.');

        $this->abortUnlessFieldsEditable($request, collect($request->validated('field_values', []))->keys()->all(), $stage);

        $fieldValues = array_merge($stage->field_values ?? [], $request->validated('field_values', []));

        if ($stage->stage === ProjectStageEnum::Execution) {
            $fieldValues['duration'] = $this->computeExecutionDuration($fieldValues);
        }

        if ($stage->stage === ProjectStageEnum::Closing) {
            $fieldValues = $this->populateClosingDates($fieldValues);
        }

        $stage->update([
            'field_values' => $fieldValues,
            'status' => $stage->status === 'Pending' ? 'In Progress' : $stage->status,
            'started_at' => $stage->started_at ?? now(),
        ]);

        return back()->with('success', 'Stage updated.');
    }

    public function complete(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        $this->authorize('completeStage', [$project, $stage->stage]);
        abort_if($stage->project_id !== $project->id, 403);
        abort_if($project->current_stage !== $stage->stage, 422, 'This stage is not currently active.');

        $stage->load(['isps.networkFeasibility.tasks', 'stageTasks']);
        $fieldValues = $stage->field_values ?? [];
        $missingFields = collect(ProjectStageFieldDefinitions::forStage($stage->stage))
            ->reject(fn (array $field): bool => $this->fieldIsFilled($stage, $field, $fieldValues[$field['key']] ?? null))
            ->pluck('key')
            ->all();
        abort_if($missingFields !== [], 422, 'Complete all required fields before advancing this stage.');

        $this->ensureRequiredNotesProvided($stage, $fieldValues);

        app(StageDocumentRequirements::class)->ensureProjectStageSatisfied($stage);

        $stage->update([
            'status' => 'Completed',
            'completed_at' => now(),
            'completed_by_id' => $request->user()->id,
        ]);

        $next = $stage->stage->next();

        if ($next === null) {
            $project->update(['current_stage' => $stage->stage, 'status' => 'Completed', 'completed_at' => now()]);
        } else {
            $project->update(['current_stage' => $next]);
        }

        if (in_array($stage->stage, [ProjectStageEnum::Planning, ProjectStageEnum::Execution], true)) {
            $this->seedIspsForNextStage($project, $next, $stage->isps);
        }

        // Monitoring's Infrastructure Deployment Status checklist depends on which of the three
        // Annex 2.b/2.b.i/2.b.ii matrices matches the pipeline's design_plan_type — set earlier,
        // during Site Planning (SitePlanningAssignment::design_plan_type), and guaranteed to be
        // set by now since it's required to complete Plans/Drawing there before the pipeline
        // could even reach Commercials/Approval/this Project in the first place.
        if ($stage->stage === ProjectStageEnum::Planning) {
            $monitoringStage = $project->stages()->where('stage', ProjectStageEnum::Monitoring->value)->first();

            if ($monitoringStage !== null) {
                // Matched on the same tower as this Project, not just the pipeline's default
                // (untowered) assignment — a multi-tower site has one SitePlanningAssignment
                // per tower, each potentially with its own design_plan_type.
                $type = SitePlanningAssignment::query()
                    ->where('pipeline_id', $project->pipeline_id)
                    ->where('tower_id', $project->tower_id)
                    ->value('design_plan_type');
                StageTaskSeeder::seedMissingFor($monitoringStage, $type);
            }
        }

        return back()->with('success', 'Stage completed.');
    }

    public function reopen(Project $project, ProjectStage $stage): RedirectResponse
    {
        $this->authorize('reopenStage', [$project, $stage->stage]);
        abort_if($stage->project_id !== $project->id, 403);
        abort_if($stage->status !== 'Completed', 422, 'Only a completed stage can be reopened.');
        abort_if($project->current_stage !== $stage->stage->next(), 422, 'Only the most recently completed stage can be reopened.');

        $stage->update(['status' => 'In Progress', 'completed_at' => null, 'completed_by_id' => null]);
        $project->update(['current_stage' => $stage->stage, 'status' => 'Active']);

        return back()->with('success', 'Stage reopened.');
    }

    public function uploadDocument(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        $this->authorize('manageStage', [$project, $stage->stage]);
        abort_if($stage->project_id !== $project->id, 403);
        abort_if($project->current_stage !== $stage->stage, 422, 'This stage is not currently active.');

        // Required-document categories (StageDocumentRequirement) plus any category a field
        // declares for itself (document_category) — the latter unlocks an optional upload
        // widget alongside a field (e.g. Monitoring's Testing) without making it required to
        // complete the stage, unlike a StageDocumentRequirement row.
        $validCategories = app(StageDocumentRequirements::class)->activeForProjectStage($stage->stage)->pluck('name')
            ->merge(collect(ProjectStageFieldDefinitions::forStage($stage->stage))->pluck('document_category')->filter())
            ->unique()
            ->values()
            ->toArray();

        $validated = $request->validate([
            'document_category' => ['required', 'string', Rule::in($validCategories)],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        $this->abortUnlessDocumentCategoryEditable($request, $stage, $validated['document_category']);

        foreach ($validated['files'] as $file) {
            $document = $this->documents->store($file, 'project-stage-documents', auth()->user(), $validated['document_category'], $stage->stage->value);
            $stage->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
        }

        if ($stage->stage === ProjectStageEnum::Planning && in_array($validated['document_category'], ['MOA', 'NTP'], true)) {
            $this->seedJoChecklistsOnceMoaAndNtpComplete($stage);
        }

        return back()->with('success', 'Document uploaded.');
    }

    /**
     * When MOA/NTP weren't already attached at the pipeline's Commercials stage,
     * InitializeProject::carryForwardIsps leaves every linked ISP's JO checklist unseeded — this
     * is the deferred trigger: once both MOA and NTP are uploaded directly here on Planning, seed
     * the checklist for every linked ISP that doesn't have one yet (NetworkFeasibilityTaskSeeder
     * is idempotent, so re-uploading MOA/NTP again later never re-seeds or duplicates tasks).
     * Manually-added ISPs (no network_feasibility_id) have no checklist to seed and are skipped.
     */
    private function seedJoChecklistsOnceMoaAndNtpComplete(ProjectStage $stage): void
    {
        $attached = $stage->documents()->whereIn('category', ['MOA', 'NTP'])->pluck('category')->unique();

        if (! $attached->contains('MOA') || ! $attached->contains('NTP')) {
            return;
        }

        $stage->isps()->with('networkFeasibility')->get()->each(function (ProjectStageIsp $isp): void {
            if ($isp->networkFeasibility !== null) {
                NetworkFeasibilityTaskSeeder::seedMissingFor($isp->networkFeasibility);
            }
        });
    }

    public function deleteDocument(Request $request, Project $project, ProjectStage $stage, Document $document): RedirectResponse
    {
        $this->authorize('manageStage', [$project, $stage->stage]);
        abort_if($stage->project_id !== $project->id, 403);
        abort_unless($stage->documents()->where('document_id', $document->id)->exists(), 403);
        $this->abortUnlessDocumentCategoryEditable($request, $stage, $document->category);

        // MOA/NTP carried forward from the pipeline's Commercials stage (InitializeProject) are the
        // site's contractual record — they can be replaced with a newer version but never removed.
        if (in_array($document->category, ['MOA', 'NTP'], true)
            && $project->pipeline->documents()->where('documents.id', $document->id)->exists()) {
            throw ValidationException::withMessages([
                'document' => "Cannot remove the {$document->category} uploaded during the Commercials stage. Replace it instead.",
            ]);
        }

        if ($document->category !== null) {
            $requiredCategories = app(StageDocumentRequirements::class)->requiredForProjectStage($stage->stage)->pluck('name');

            if ($requiredCategories->contains($document->category)) {
                throw ValidationException::withMessages([
                    'document' => "Cannot remove a required document ({$document->category}) for this stage.",
                ]);
            }
        }

        $stage->documents()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }

        return back()->with('success', 'Document removed.');
    }

    public function replaceDocument(Request $request, Project $project, ProjectStage $stage, Document $document): RedirectResponse
    {
        $this->authorize('manageStage', [$project, $stage->stage]);
        abort_if($stage->project_id !== $project->id, 403);
        abort_unless($stage->documents()->where('document_id', $document->id)->exists(), 403);
        $this->abortUnlessDocumentCategoryEditable($request, $stage, $document->category);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'project-stage-documents');

        return back()->with('success', 'Document replaced.');
    }

    public function updateDocumentStatus(Request $request, Project $project, ProjectStage $stage, Document $document): RedirectResponse
    {
        $this->authorize('manageStage', [$project, $stage->stage]);
        abort_if($stage->project_id !== $project->id, 403);
        abort_unless($stage->documents()->where('document_id', $document->id)->exists(), 403);

        $field = collect(ProjectStageFieldDefinitions::forStage($stage->stage))
            ->first(fn (array $f): bool => ($f['document_category'] ?? null) === $document->category);

        abort_if($field === null, 422, 'This document has no status field on the current stage.');

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in($field['options'] ?? [])],
        ]);

        $stage->documents()->updateExistingPivot($document->id, ['status' => $validated['status']]);

        return back()->with('success', 'Document status updated.');
    }

    /**
     * Seeds the next stage with one linked ProjectStageIsp per current-stage ISP
     * (source_project_stage_isp_id pointing back to the original, network_feasibility_id carried
     * forward unchanged since it's still the same Network Site ISP) — used for both
     * Planning→Execution and Execution→Monitoring, so the project team isn't retyping the same
     * ISP list at every stage. There's no separate task checklist to seed per stage anymore —
     * every stage's ISP row reads its slice of the same Network Site ISP's JO checklist
     * (ProjectStageIsp::tasksForCurrentStage). Only applies when the next stage's list is still
     * empty — doesn't clobber ISPs the team already added directly on that stage.
     *
     * @param  Collection<int, ProjectStageIsp>  $currentStageIsps
     */
    private function seedIspsForNextStage(Project $project, ProjectStageEnum $nextStage, Collection $currentStageIsps): void
    {
        if ($currentStageIsps->isEmpty()) {
            return;
        }

        $nextProjectStage = $project->stages()->where('stage', $nextStage)->first();

        if ($nextProjectStage === null || $nextProjectStage->isps()->exists()) {
            return;
        }

        foreach ($currentStageIsps as $currentIsp) {
            $nextProjectStage->isps()->create([
                'isp_name' => $currentIsp->isp_name,
                'source_project_stage_isp_id' => $currentIsp->id,
                'network_feasibility_id' => $currentIsp->network_feasibility_id,
            ]);
        }
    }

    /**
     * Auto-computes Execution's read-only Duration field as the calendar day span between
     * mobilization_schedule and target_finished_date, so it never has to be typed manually and
     * always stays consistent with those two dates. Null when either date is missing/unparseable
     * or the target date precedes the mobilization date.
     *
     * @param  array<string, mixed>  $fieldValues
     */
    private function computeExecutionDuration(array $fieldValues): ?string
    {
        $mobilization = $fieldValues['mobilization_schedule'] ?? null;
        $target = $fieldValues['target_finished_date'] ?? null;

        if (! filled($mobilization) || ! filled($target)) {
            return null;
        }

        try {
            $mobilizationDate = Carbon::parse($mobilization);
            $targetDate = Carbon::parse($target);
        } catch (InvalidFormatException) {
            return null;
        }

        if ($targetDate->lt($mobilizationDate)) {
            return null;
        }

        return $mobilizationDate->diffInDays($targetDate).' days';
    }

    /**
     * Auto-fills rfco_date/acceptance_date with today's date the first time their matching
     * status flips to 'Done' — only ever writes when the date is still blank, so it's a one-time
     * convenience fill, never overwriting a date the PM already entered or later corrected.
     *
     * @param  array<string, mixed>  $fieldValues
     * @return array<string, mixed>
     */
    private function populateClosingDates(array $fieldValues): array
    {
        foreach (['rfco_status' => 'rfco_date', 'acceptance_status' => 'acceptance_date'] as $statusKey => $dateKey) {
            if (($fieldValues[$statusKey] ?? null) === 'Done' && blank($fieldValues[$dateKey] ?? null)) {
                $fieldValues[$dateKey] = now()->toDateString();
            }
        }

        return $fieldValues;
    }

    /**
     * Fields owned by another department (e.g. Planning Details is Infra's, ISP Status is
     * Network's — see ProjectStageFieldDefinitions::isEditableBy) are read-only to everyone else.
     *
     * @param  array<int, string>  $fieldKeys
     */
    private function abortUnlessFieldsEditable(Request $request, array $fieldKeys, ProjectStage $stage): void
    {
        $locked = collect(ProjectStageFieldDefinitions::forStage($stage->stage))
            ->whereIn('key', $fieldKeys)
            ->contains(fn (array $field): bool => ! ProjectStageFieldDefinitions::isEditableBy($request->user(), $field));

        abort_if($locked, 403, 'You do not have permission to edit this section.');
    }

    private function abortUnlessDocumentCategoryEditable(Request $request, ProjectStage $stage, ?string $category): void
    {
        $field = collect(ProjectStageFieldDefinitions::forStage($stage->stage))
            ->first(fn (array $field): bool => $category !== null && ($field['document_category'] ?? null) === $category);

        abort_if(
            $field !== null && ! ProjectStageFieldDefinitions::isEditableBy($request->user(), $field),
            403,
            'You do not have permission to edit this section.'
        );
    }

    /**
     * A field flagged `notes_required_when` (e.g. Closing's RFCO/Acceptance Status → On-hold)
     * must carry its remarks before the stage can complete, even when the value was saved earlier.
     *
     * @param  array<string, mixed>  $fieldValues
     */
    private function ensureRequiredNotesProvided(ProjectStage $stage, array $fieldValues): void
    {
        $errors = [];

        foreach (ProjectStageFieldDefinitions::forStage($stage->stage) as $field) {
            $triggerValues = $field['notes_required_when'] ?? [];

            if (! ($field['notes'] ?? false) || $triggerValues === []) {
                continue;
            }

            $notesKey = "{$field['key']}_notes";

            if (in_array($fieldValues[$field['key']] ?? null, $triggerValues, true) && blank($fieldValues[$notesKey] ?? null)) {
                $errors["field_values.{$notesKey}"] = "Remarks are required when {$field['label']} is ".implode('/', $triggerValues).'.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function fieldIsFilled(ProjectStage $stage, array $field, mixed $value): bool
    {
        if ($field['type'] === 'document_status') {
            return $stage->documents
                ->where('category', $field['document_category'])
                ->contains(fn (Document $document): bool => filled($document->pivot->status));
        }

        if ($field['type'] === 'document_upload') {
            return $stage->documents->where('category', $field['document_category'])->isNotEmpty();
        }

        // isp_task_list is informational only — the JO checklist it displays is actioned
        // independently in the Network module and must never block a stage from completing.
        if ($field['type'] === 'isp_task_list') {
            return true;
        }

        if ($field['type'] === 'stage_task_list') {
            $tasks = $stage->stageTasks->where('field_key', $field['key']);

            return $tasks->isNotEmpty()
                && $tasks->every(fn (StageTask $task): bool => $task->status === IspTaskStatus::Done);
        }

        return $value !== null && $value !== '';
    }
}
