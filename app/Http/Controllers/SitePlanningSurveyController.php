<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Enums\SitePlanningActionPermission;
use App\Http\Requests\CompleteSurveyRequest;
use App\Http\Requests\DeclareTowersRequest;
use App\Http\Requests\ScheduleSiteVisitRequest;
use App\Models\Document;
use App\Models\PipelineStatusLog;
use App\Models\SitePlanningAssignment;
use App\Models\SiteSurvey;
use App\Models\SiteSurveyTask;
use App\Models\Tower;
use App\Models\User;
use App\Support\DocumentUploader;
use App\Support\PipelineNotifier;
use App\Support\SitePlanningTaskSeeder;
use App\Support\SiteSurveyProgression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SitePlanningSurveyController extends SitePlanningController
{
    public function scheduleSurvey(ScheduleSiteVisitRequest $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('assignSiteSurveyor', $sitePlanningAssignment);

        $validated = $request->validated();

        abort_unless(
            User::withActionPermission(SitePlanningActionPermission::FillSurvey->value)
                ->whereKey($validated['surveyor_id'])
                ->exists(),
            422,
            'The selected user must be allowed to fill site surveys.'
        );

        // Type is locked once the checklist has been seeded — changing it afterward would orphan
        // whatever task status/notes/documents have already been recorded against the old list.
        abort_if(
            $sitePlanningAssignment->sitePlanningTasks()->exists()
                && $validated['design_plan_type'] !== $sitePlanningAssignment->design_plan_type,
            422,
            'Type is locked once the Plans/Drawing checklist has been generated.'
        );

        $pipeline = $sitePlanningAssignment->pipeline;
        $survey = $pipeline->status === PipelineStatus::SiteSurveyPending
            ? SiteSurveyProgression::enterSiteSurvey($pipeline, auth()->user())
            : SiteSurveyProgression::ensureSiteSurvey($pipeline);
        $surveyorChanged = $survey->surveyor_id !== (int) $validated['surveyor_id'];

        $survey->update([
            'surveyor_id' => $validated['surveyor_id'],
            'survey_date' => $validated['survey_date'],
            'remarks' => $validated['remarks'] ?? $survey->remarks,
        ]);

        $sitePlanningAssignment->update(['design_plan_type' => $validated['design_plan_type']]);

        if (! empty($validated['files'])) {
            foreach ($validated['files'] as $file) {
                $document = $this->documents->store($file, 'site-planning-documents', auth()->user(), $validated['document_category'] ?? null);
                $sitePlanningAssignment->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
            }
        }

        if ($surveyorChanged) {
            app(PipelineNotifier::class)->surveyorAssigned($sitePlanningAssignment, User::findOrFail($validated['surveyor_id']));
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Site survey scheduled successfully.');
    }

    public function saveInspection(CompleteSurveyRequest $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('fillSurvey', $sitePlanningAssignment);

        $this->persistInspectionData($request, $sitePlanningAssignment);

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Survey findings saved.');
    }

    /**
     * Declares how many physical towers/buildings this site actually has, once the Surveyor has
     * confirmed it on-site — the commercial Pipeline/deal stays one per site, but from this point
     * on each tower gets its own SiteSurvey, SitePlanningAssignment, and (once approved) Project,
     * so Infra can track survey/build progress per tower independently. Reuses this assignment's
     * existing SiteSurvey row for the first declared tower (avoids leaving it orphaned) and
     * creates new SiteSurvey/SitePlanningAssignment rows for every tower after the first.
     * One-time: rejected once the site already has towers declared, or if called from anything
     * but the site's original untowered assignment.
     */
    public function declareTowers(DeclareTowersRequest $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('fillSurvey', $sitePlanningAssignment);

        $pipeline = $sitePlanningAssignment->pipeline;
        $siteProfile = $pipeline->siteProfile;

        abort_if($siteProfile->towers()->exists(), 422, 'Towers have already been declared for this site.');
        abort_if($sitePlanningAssignment->tower_id !== null, 422, 'Towers can only be declared from the site\'s original assignment.');

        $towers = collect($request->validated('towers'))
            ->map(fn (array $tower) => Tower::create([
                'site_profile_id' => $siteProfile->id,
                'name' => $tower['name'],
                'total_units' => $tower['total_units'] ?? null,
                'total_floors' => $tower['total_floors'] ?? null,
            ]));

        $firstTower = $towers->first();
        // The declared per-tower totals supersede the SAQ's site-level figures: each
        // tower's survey starts from its own declared floors/units — still editable on-site.
        $sitePlanningAssignment->siteSurvey?->update([
            'tower_id' => $firstTower->id,
            'floors_surveyed' => $firstTower->total_floors ?? $sitePlanningAssignment->siteSurvey->floors_surveyed,
            'actual_unit_count' => $firstTower->total_units ?? $sitePlanningAssignment->siteSurvey->actual_unit_count,
        ]);
        $sitePlanningAssignment->update(['tower_id' => $firstTower->id]);

        $originalSurvey = $sitePlanningAssignment->siteSurvey;

        foreach ($towers->skip(1) as $tower) {
            $survey = SiteSurvey::create([
                'site_profile_id' => $siteProfile->id,
                'pipeline_id' => $pipeline->id,
                'tower_id' => $tower->id,
                // Same on-site visit, same surveyor, until the Infra Manager reassigns this
                // tower's assignment to someone else via ScheduleForm — without this, the
                // survey's surveyor_id stays null and SitePlanningPolicy::fillSurvey() locks
                // everyone (including the original surveyor) out of the new tower's assignment.
                'surveyor_id' => $originalSurvey?->surveyor_id,
                'survey_date' => $originalSurvey?->survey_date,
                'floors_surveyed' => $tower->total_floors,
                'actual_unit_count' => $tower->total_units,
            ]);

            $towerAssignment = SitePlanningAssignment::ensureFor($pipeline, $survey);

            // Same default reasoning as surveyor_id above: without a design_plan_type,
            // SitePlanningTaskSeeder::seedMissingFor() silently no-ops on this tower's inspection
            // submit, leaving it stuck with no Plans/Drawing checklist and no way to proceed. The
            // Infra Manager can still override it per tower via ScheduleForm before any checklist
            // is seeded (scheduleSurvey locks the type only once tasks exist).
            $towerAssignment->update(['design_plan_type' => $sitePlanningAssignment->design_plan_type]);
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', "Declared {$towers->count()} towers for this site.");
    }

    /**
     * Submitting the inspection form no longer completes the survey outright — it hands off to
     * the Plans/Drawing checklist (seeded here, idempotently, using the Type already chosen at
     * scheduling time), worked by an Infra Designer. The survey itself is only completed later,
     * by completeDesignPlan().
     */
    public function submitInspection(CompleteSurveyRequest $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('submitInspection', $sitePlanningAssignment);

        $this->persistInspectionData($request, $sitePlanningAssignment);
        $this->ensureRequiredDocumentsAreAttached($sitePlanningAssignment);

        $hadNoTasksYet = $sitePlanningAssignment->sitePlanningTasks()->doesntExist();
        SitePlanningTaskSeeder::seedMissingFor($sitePlanningAssignment);

        if (
            $hadNoTasksYet
            && $sitePlanningAssignment->design_plan_assignee_id === null
            && $sitePlanningAssignment->sitePlanningTasks()->exists()
        ) {
            app(PipelineNotifier::class)->designPlanAssignmentNeeded($sitePlanningAssignment, auth()->user());
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Inspection submitted. Sent to Plans/Drawing.');
    }

    public function revertToProspecting(SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('fillSurvey', $sitePlanningAssignment);

        $pipeline = $sitePlanningAssignment->pipeline;
        abort_if($pipeline->status !== PipelineStatus::SiteSurvey, 422, 'Pipeline is not in the correct status.');

        $pipeline->update(['status' => PipelineStatus::Prospecting]);
        PipelineStatusLog::record($pipeline, PipelineStatus::Prospecting);

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Reverted to Prospecting.');
    }

    public function updateSurveyTaskNotes(Request $request, SitePlanningAssignment $sitePlanningAssignment, SiteSurveyTask $siteSurveyTask): RedirectResponse
    {
        $this->authorize('fillSurvey', $sitePlanningAssignment);
        $this->ensureSurveyTaskBelongsToAssignment($sitePlanningAssignment, $siteSurveyTask);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $siteSurveyTask->update($validated);

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Task notes saved.');
    }

    public function uploadSurveyTaskDocument(Request $request, SitePlanningAssignment $sitePlanningAssignment, SiteSurveyTask $siteSurveyTask): RedirectResponse
    {
        $this->authorize('fillSurvey', $sitePlanningAssignment);
        $this->ensureSurveyTaskBelongsToAssignment($sitePlanningAssignment, $siteSurveyTask);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ]);

        foreach ($validated['files'] as $file) {
            $document = $this->documents->store($file, 'site-planning-documents', auth()->user(), $siteSurveyTask->name);
            $siteSurveyTask->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document uploaded.');
    }

    public function deleteSurveyTaskDocument(SitePlanningAssignment $sitePlanningAssignment, SiteSurveyTask $siteSurveyTask, Document $document): RedirectResponse
    {
        $this->authorize('fillSurvey', $sitePlanningAssignment);
        $this->ensureSurveyTaskBelongsToAssignment($sitePlanningAssignment, $siteSurveyTask);
        abort_unless($siteSurveyTask->documents()->where('document_id', $document->id)->exists(), 403);

        $siteSurveyTask->documents()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document removed.');
    }

    public function replaceSurveyTaskDocument(Request $request, SitePlanningAssignment $sitePlanningAssignment, SiteSurveyTask $siteSurveyTask, Document $document): RedirectResponse
    {
        $this->authorize('fillSurvey', $sitePlanningAssignment);
        $this->ensureSurveyTaskBelongsToAssignment($sitePlanningAssignment, $siteSurveyTask);
        abort_unless($siteSurveyTask->documents()->where('document_id', $document->id)->exists(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'site-planning-documents');

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document replaced.');
    }

    public function uploadDocument(Request $request, SitePlanningAssignment $sitePlanningAssignment): RedirectResponse
    {
        $this->authorize('manageDocuments', $sitePlanningAssignment);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
            'document_category' => ['nullable', 'string'],
        ]);

        foreach ($validated['files'] as $file) {
            $document = $this->documents->store($file, 'site-planning-documents', auth()->user(), $validated['document_category'] ?? null);
            $sitePlanningAssignment->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document uploaded.');
    }

    public function deleteDocument(SitePlanningAssignment $sitePlanningAssignment, Document $document): RedirectResponse
    {
        $this->authorize('manageDocuments', $sitePlanningAssignment);

        abort_unless($sitePlanningAssignment->documents()->where('document_id', $document->id)->exists(), 403);

        $sitePlanningAssignment->documents()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document removed.');
    }

    public function replaceDocument(Request $request, SitePlanningAssignment $sitePlanningAssignment, Document $document): RedirectResponse
    {
        $this->authorize('manageDocuments', $sitePlanningAssignment);

        abort_unless($sitePlanningAssignment->documents()->where('document_id', $document->id)->exists(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'site-planning-documents');

        return redirect()->route('site-planning.show', $sitePlanningAssignment)
            ->with('success', 'Document replaced.');
    }
}
