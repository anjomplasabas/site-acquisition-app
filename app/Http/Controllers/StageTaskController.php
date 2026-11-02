<?php

namespace App\Http\Controllers;

use App\Enums\IspTaskStatus;
use App\Enums\ProjectStage as ProjectStageEnum;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\StageTask;
use App\Support\DocumentUploader;
use App\Support\ProjectStageFieldDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StageTaskController extends Controller
{
    public function __construct(private readonly DocumentUploader $documents) {}

    public function updateStatus(Request $request, Project $project, ProjectStage $stage, StageTask $stageTask): RedirectResponse
    {
        $this->authorizeManage($project, $stage);
        $this->ensureTaskBelongsToStage($stage, $stageTask);
        $this->abortUnlessTaskFieldEditable($request, $stage, $stageTask->field_key);

        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', Rule::in(array_column(IspTaskStatus::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $attributes = [];
        if (array_key_exists('status', $validated) && $validated['status'] !== $stageTask->status->value) {
            $attributes['status'] = $validated['status'];
            $attributes['status_changed_at'] = now();
        }
        if (array_key_exists('notes', $validated)) {
            $attributes['notes'] = $validated['notes'];
        }
        if ($attributes !== []) {
            $stageTask->update($attributes);
        }

        if ($stage->stage === ProjectStageEnum::Monitoring && $stageTask->field_key === 'infrastructure_deployment_status') {
            $stage->syncProjectStatusFromInfrastructure();
        }

        return back()->with('success', 'Task updated.');
    }

    /**
     * Marks every StageTask in a given stage_task_list field as Done in one request — a
     * shortcut for large checklists (Planning's ~29-item Plans/Drawing, Monitoring's Infrastructure
     * Deployment Status) instead of updating each task's status individually. Scoped to one
     * `section` (a phase like "Initial Draft"/"MDF Installation") when the request sends one —
     * StageTaskListField.vue always sends a section today, since every current checklist groups
     * its tasks into sections; `section` is optional here only so a future sectionless
     * stage_task_list field (none exist today) still has a working bulk-complete affordance via
     * an explicit `null` section. Mirrors updateStatus()'s behavior exactly: only bumps
     * status_changed_at for rows actually changing (already-Done tasks are left untouched), and
     * replicates Monitoring's project_status auto-sync side effect for parity.
     */
    public function completeAll(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        $this->authorizeManage($project, $stage);

        $validated = $request->validate([
            'field_key' => ['required', 'string'],
            'section' => ['sometimes', 'nullable', 'string'],
        ]);

        $this->abortUnlessTaskFieldEditable($request, $stage, $validated['field_key']);

        $query = $stage->stageTasks()
            ->where('field_key', $validated['field_key'])
            ->where('status', '!=', IspTaskStatus::Done->value);

        if ($request->has('section')) {
            $validated['section'] === null ? $query->whereNull('section') : $query->where('section', $validated['section']);
        }

        $query->update(['status' => IspTaskStatus::Done->value, 'status_changed_at' => now()]);

        if ($stage->stage === ProjectStageEnum::Monitoring && $validated['field_key'] === 'infrastructure_deployment_status') {
            $stage->syncProjectStatusFromInfrastructure();
        }

        return back()->with('success', 'All tasks marked Done.');
    }

    /**
     * Keeps Monitoring's Project Status dropdown in sync with the Infrastructure Deployment
     * Status checklist — 'On-going' once any task starts, 'Done' once every task is Done. Skipped
     * whenever the PM has manually put the project 'On-hold', which acts as a manual override
     * until they pick something else themselves.
     */
    public function uploadDocument(Request $request, Project $project, ProjectStage $stage, StageTask $stageTask): RedirectResponse
    {
        $this->authorizeManage($project, $stage);
        $this->ensureTaskBelongsToStage($stage, $stageTask);
        $this->abortUnlessTaskFieldEditable($request, $stage, $stageTask->field_key);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        foreach ($validated['files'] as $file) {
            $document = $this->documents->store($file, 'stage-task-documents', auth()->user(), $stageTask->name, $stage->stage->value);
            $stageTask->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
        }

        return back()->with('success', 'Document uploaded.');
    }

    public function deleteDocument(Request $request, Project $project, ProjectStage $stage, StageTask $stageTask, Document $document): RedirectResponse
    {
        $this->authorizeManage($project, $stage);
        $this->ensureTaskBelongsToStage($stage, $stageTask);
        $this->abortUnlessTaskFieldEditable($request, $stage, $stageTask->field_key);
        abort_unless($stageTask->documents()->where('document_id', $document->id)->exists(), 403);

        $stageTask->documents()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }

        return back()->with('success', 'Document removed.');
    }

    public function replaceDocument(Request $request, Project $project, ProjectStage $stage, StageTask $stageTask, Document $document): RedirectResponse
    {
        $this->authorizeManage($project, $stage);
        $this->ensureTaskBelongsToStage($stage, $stageTask);
        $this->abortUnlessTaskFieldEditable($request, $stage, $stageTask->field_key);
        abort_unless($stageTask->documents()->where('document_id', $document->id)->exists(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'stage-task-documents');

        return back()->with('success', 'Document replaced.');
    }

    private function authorizeManage(Project $project, ProjectStage $stage): void
    {
        $this->authorize('manageStage', [$project, $stage->stage]);
        abort_if($stage->project_id !== $project->id, 403);
        abort_if($project->current_stage !== $stage->stage, 422, 'This stage is not currently active.');
    }

    private function ensureTaskBelongsToStage(ProjectStage $stage, StageTask $stageTask): void
    {
        abort_if($stageTask->project_stage_id !== $stage->id, 403);
    }

    /**
     * Mirrors ProjectStageController's field-level ownership checks: a stage_task_list
     * field owned by Infra (e.g. Monitoring's Infrastructure Deployment Status) must not be
     * actionable by Network users, even though they hold manageStage for the stage.
     */
    private function abortUnlessTaskFieldEditable(Request $request, ProjectStage $stage, string $fieldKey): void
    {
        $field = collect(ProjectStageFieldDefinitions::forStage($stage->stage))
            ->first(fn (array $field): bool => $field['key'] === $fieldKey);

        abort_if(
            $field !== null && ! ProjectStageFieldDefinitions::isEditableBy($request->user(), $field),
            403,
            'You do not have permission to edit this section.'
        );
    }
}
