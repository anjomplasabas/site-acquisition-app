<?php

namespace App\Console\Commands;

use App\Enums\ProjectStage as ProjectStageEnum;
use App\Models\ProjectStage;
use App\Support\StageTaskSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off, re-runnable fix for Monitoring's "Infrastructure Deployment Status" checklist: it used
 * to be a single hardcoded, non-type-aware list (StageTaskDefinitions' old
 * MONITORING_INFRASTRUCTURE_TASKS) seeded unconditionally at project creation. It's now one of
 * three type-specific matrices (Annex 2.b/2.b.i/2.b.ii) tied to Planning's design_plan_type,
 * seeded only once Planning completes. Existing projects still carry the old, incorrect
 * checklist — this command replaces it with the correct one for every project whose Planning
 * stage already has a design_plan_type set, discarding old task progress/attachments (they were
 * tracked against the wrong list anyway).
 *
 * Frozen against the old data shape intentionally: design_plan_type has since moved to the Site
 * Planning module (SitePlanningAssignment::design_plan_type, set before Commercials/Approval),
 * and ProjectStageController::complete() now seeds Monitoring's checklist from that new location
 * directly for every project going forward. This command remains a one-time fix for projects
 * created before that move, whose Planning field_values genuinely still carry the old value —
 * it is not expected to be re-run for projects created after the move.
 */
#[Signature('app:backfill-monitoring-infrastructure-tasks')]
#[Description("Replace each project's Monitoring Infrastructure Deployment Status checklist with the correct type-based one")]
class BackfillMonitoringInfrastructureTasks extends Command
{
    public function handle(): int
    {
        $replaced = 0;
        $skipped = 0;

        ProjectStage::query()
            ->where('stage', ProjectStageEnum::Monitoring->value)
            ->with('project.stages')
            ->chunkById(100, function ($monitoringStages) use (&$replaced, &$skipped): void {
                foreach ($monitoringStages as $monitoringStage) {
                    $planningStage = $monitoringStage->project->stages->firstWhere('stage', ProjectStageEnum::Planning);
                    $type = $planningStage?->field_values['design_plan_type'] ?? null;

                    if ($type === null) {
                        $skipped++;

                        continue;
                    }

                    $this->replaceInfrastructureTasks($monitoringStage, $type);
                    $replaced++;
                }
            });

        $this->info("Replaced {$replaced} Monitoring Infrastructure checklist(s). Skipped {$skipped} project(s) with no Planning design_plan_type set yet.");

        return self::SUCCESS;
    }

    private function replaceInfrastructureTasks(ProjectStage $monitoringStage, string $type): void
    {
        $existingTasks = $monitoringStage->stageTasks()->where('field_key', 'infrastructure_deployment_status')->get();

        foreach ($existingTasks as $task) {
            foreach ($task->documents as $document) {
                $task->documents()->detach($document->id);

                if ($document->isOrphaned()) {
                    Storage::disk((string) config('filesystems.documents_disk', 'public'))->delete($document->file_path);
                    $document->delete();
                }
            }

            $task->delete();
        }

        StageTaskSeeder::seedMissingFor($monitoringStage->fresh(), $type);
    }
}
