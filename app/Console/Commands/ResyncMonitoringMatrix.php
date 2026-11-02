<?php

namespace App\Console\Commands;

use App\Enums\ProjectStage as ProjectStageEnum;
use App\Models\Project;
use App\Models\SitePlanningAssignment;
use App\Support\ChecklistReseeder;
use App\Support\StageTaskDefinitions;
use App\Support\StageTaskSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off, idempotent resync for projects whose Monitoring "Infrastructure Deployment Status"
 * checklist was seeded from the wrong design_plan_type matrix — e.g. a Residential site
 * carrying the FAMILIA fiber task list. Rebuilds the checklist from the tower's current
 * SitePlanningAssignment type via StageTaskSeeder::reseedFor(), which carries over
 * status/notes/documents for tasks present in both matrices. Projects whose checklist already
 * matches their type's template set are skipped, so re-runs report 0. FIBER intentionally maps
 * to the Residential matrix (see StageTaskDefinitions::forField) — that is not a mismatch.
 */
#[Signature('app:resync-monitoring-matrix')]
#[Description('Rebuild Monitoring Infrastructure checklists seeded from the wrong design plan type')]
class ResyncMonitoringMatrix extends Command
{
    private const FIELD_KEY = 'infrastructure_deployment_status';

    public function handle(): int
    {
        $resynced = 0;
        $skipped = 0;

        Project::query()->with('stages')->chunkById(100, function ($projects) use (&$resynced, &$skipped): void {
            foreach ($projects as $project) {
                $monitoringStage = $project->stages->firstWhere('stage', ProjectStageEnum::Monitoring);

                if ($monitoringStage === null) {
                    continue;
                }

                $actual = $monitoringStage->stageTasks()
                    ->where('field_key', self::FIELD_KEY)
                    ->orderBy('sort_order')
                    ->get(['section', 'name'])
                    ->map(fn ($task): string => ChecklistReseeder::carryOverKey($task->section, $task->name))
                    ->all();

                if ($actual === []) {
                    continue;
                }

                $type = SitePlanningAssignment::query()
                    ->where('pipeline_id', $project->pipeline_id)
                    ->where('tower_id', $project->tower_id)
                    ->value('design_plan_type');

                if ($type === null) {
                    continue;
                }

                $expected = collect(StageTaskDefinitions::forField(ProjectStageEnum::Monitoring, self::FIELD_KEY, $type))
                    ->map(fn (array $task): string => ChecklistReseeder::carryOverKey($task['section'] ?? null, $task['name']))
                    ->all();

                if ($actual === $expected) {
                    $skipped++;

                    continue;
                }

                StageTaskSeeder::reseedFor($monitoringStage, self::FIELD_KEY, $type);
                $resynced++;
            }
        });

        $this->info("Resynced {$resynced} Monitoring checklist(s); {$skipped} already matched.");

        return self::SUCCESS;
    }
}
