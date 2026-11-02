<?php

namespace App\Listeners;

use App\Enums\ProjectStage as ProjectStageEnum;
use App\Events\PipelineApproved;
use App\Models\NetworkFeasibility;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Tower;
use App\Support\NetworkFeasibilityTaskSeeder;
use App\Support\StageTaskSeeder;

class InitializeProject
{
    public function handle(PipelineApproved $event): void
    {
        $pipeline = $event->pipeline;
        $towers = $pipeline->towers;

        // A site with no declared towers (the common case) gets exactly one, untowered Project —
        // identical to pre-tower behavior. A site with 2+ towers gets one independent Project
        // per tower instead, each progressing through its own 4 stages separately.
        $anyCreated = $towers->isEmpty()
            ? $this->createProjectForTower($pipeline, null)
            : collect($towers)->map(fn (Tower $tower) => $this->createProjectForTower($pipeline, $tower))->contains(true);

        // Network Site assignment is pipeline-level, not per-tower (§FR-NET) — attach once, and
        // only the first time any Project is actually created (re-firing this listener on an
        // already-initialized pipeline must stay a no-op, matching the old single-project guard).
        if ($anyCreated) {
            $this->carryForwardMoaNtpDocumentsToNetworkSite($pipeline);
        }
    }

    private function createProjectForTower(Pipeline $pipeline, ?Tower $tower): bool
    {
        $alreadyExists = Project::query()
            ->where('pipeline_id', $pipeline->id)
            ->where('tower_id', $tower?->id)
            ->exists();

        if ($alreadyExists) {
            return false;
        }

        $project = Project::create([
            'pipeline_id' => $pipeline->id,
            'tower_id' => $tower?->id,
            'started_at' => now(),
        ]);

        foreach (ProjectStageEnum::ordered() as $stageEnum) {
            $stage = ProjectStage::create([
                'project_id' => $project->id,
                'stage' => $stageEnum,
                'status' => 'Pending',
                'order' => $stageEnum->order(),
            ]);

            // Seeds every stage_task_list field on this stage (Monitoring's Infrastructure
            // Deployment Status, Planning's Plans/Drawing design-plan checklist) up front —
            // unlike Planning/Execution's ISP checklists, these aren't sourced from pipeline or
            // prior-stage data, so they don't need to wait for an earlier stage to complete. A
            // stage/field with no fixed definition seeds nothing, a safe no-op for
            // Execution/Closing. Shared with the stage-tasks:backfill command, which calls the
            // same helper for ProjectStage rows created before a given field existed.
            StageTaskSeeder::seedMissingFor($stage);

            // The Surveyor assigned during Site Survey is the Project's PM In-charge by default.
            if ($stageEnum === ProjectStageEnum::Execution) {
                $surveyorId = $this->assignedSurveyorId($pipeline, $tower);

                if ($surveyorId !== null) {
                    $stage->update(['field_values' => ['pm_in_charge' => $surveyorId]]);
                }
            }

            if ($stageEnum === ProjectStageEnum::Planning) {
                $this->carryForwardIsps($pipeline, $stage);
                $this->carryForwardMoaNtpDocuments($pipeline, $stage);
            }
        }

        return true;
    }

    private function assignedSurveyorId(Pipeline $pipeline, ?Tower $tower): ?int
    {
        $survey = $tower === null
            ? $pipeline->siteSurvey
            : $pipeline->sitePlanningAssignments()->where('tower_id', $tower->id)->first()?->siteSurvey;

        return $survey?->surveyor_id;
    }

    /**
     * Seeds the Planning stage's ISP list 1:1 from Pipeline's NetworkFeasibility rows so the
     * project team doesn't re-enter what Site Survey already captured — one ProjectStageIsp per
     * ISP the pipeline recorded (a pipeline with none seeds an empty list; the team adds ISPs
     * manually from there), linked back to its originating Network Site ISP via
     * network_feasibility_id. Pipeline's ProviderStatus (Checking/Feasible/Non Feasible) has no
     * equivalent on the Project side, so it isn't carried over.
     *
     * Each Network Site ISP also gets its JO checklist seeded here, but only when the pipeline
     * already had both MOA and NTP attached at Commercials — if not, the checklist is left empty
     * and instead gets seeded later, the moment MOA and NTP are both uploaded directly on the
     * Planning stage (ProjectStageController::uploadDocument). Either way,
     * NetworkFeasibilityTaskSeeder is the single place that actually inserts the tasks.
     */
    private function carryForwardIsps(Pipeline $pipeline, ProjectStage $planningStage): void
    {
        $feasibilities = $pipeline->siteSurvey?->networkFeasibilities ?? collect();
        $hasMoaAndNtp = $this->pipelineHasMoaAndNtp($pipeline);

        foreach ($feasibilities as $feasibility) {
            /** @var NetworkFeasibility $feasibility */
            $planningStage->isps()->create([
                'isp_name' => $feasibility->isp_name,
                'notes' => $feasibility->notes,
                'network_feasibility_id' => $feasibility->id,
            ]);

            if ($hasMoaAndNtp) {
                NetworkFeasibilityTaskSeeder::seedMissingFor($feasibility);
            }
        }
    }

    private function pipelineHasMoaAndNtp(Pipeline $pipeline): bool
    {
        $categories = $pipeline->documents()->whereIn('category', ['MOA', 'NTP'])->pluck('category')->unique();

        return $categories->contains('MOA') && $categories->contains('NTP');
    }

    /**
     * Attaches the pipeline's most recent MOA and NTP documents onto the Network Site the same
     * way carryForwardMoaNtpDocuments() shares them onto the Project's Planning stage — same
     * Document row, no duplication.
     */
    private function carryForwardMoaNtpDocumentsToNetworkSite(Pipeline $pipeline): void
    {
        $assignment = $pipeline->networkSiteAssignment;

        if ($assignment === null) {
            return;
        }

        foreach (['MOA', 'NTP'] as $category) {
            $document = $pipeline->documents()
                ->where('category', $category)
                ->orderByDesc('documents.created_at')
                ->orderByDesc('documents.id')
                ->first();

            if ($document !== null) {
                $assignment->documents()->attach($document->id, ['created_at' => now()]);
            }
        }
    }

    /**
     * Attaches the pipeline's most recent MOA and NTP documents (uploaded at Commercials,
     * app/Http/Controllers/PipelineController.php::storeDocument) directly onto the new
     * Planning stage — the same Document row shared via project_stage_documents, no file
     * duplication, mirroring how a Document can already belong to a Pipeline, a SiteSurvey,
     * and a ProjectStage at once. Status starts null; the project team sets Planning's
     * MOA/NTP progress themselves via ProjectStageController::updateDocumentStatus — inheriting
     * the file isn't the same as inheriting a progress value. If the pipeline re-uploaded a
     * category more than once, only the most recently created one carries over. A pipeline
     * with no MOA/NTP document simply leaves that category unattached, same as before.
     */
    private function carryForwardMoaNtpDocuments(Pipeline $pipeline, ProjectStage $planningStage): void
    {
        foreach (['MOA', 'NTP'] as $category) {
            $document = $pipeline->documents()
                ->where('category', $category)
                ->orderByDesc('documents.created_at')
                ->orderByDesc('documents.id')
                ->first();

            if ($document !== null) {
                $planningStage->documents()->attach($document->id, ['created_at' => now()]);
            }
        }
    }
}
