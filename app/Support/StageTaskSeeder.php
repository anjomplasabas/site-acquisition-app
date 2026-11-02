<?php

namespace App\Support;

use App\Enums\IspTaskStatus;
use App\Models\ProjectStage;

/**
 * Seeds a ProjectStage's fixed stage_task_list checklists (StageTaskDefinitions::forField) —
 * used both at project creation time (InitializeProject) and, idempotently, to backfill any
 * ProjectStage that was created before a given stage_task_list field existed (see the
 * stage-tasks:backfill Artisan command).
 *
 * `$type` is the pipeline's design_plan_type value (set during Site Planning, see
 * SitePlanningAssignment::design_plan_type) — required to pick which of the three Annex
 * 2.b/2.b.i/2.b.ii matrices seeds Monitoring's Infrastructure Deployment Status checklist
 * (StageTaskDefinitions::forField returns [] for Monitoring's infra field when `$type` is null,
 * so this is a safe no-op until the type is known — see ProjectStageController::complete, which
 * seeds Monitoring only once Planning completes and the pipeline's finalized type is available).
 */
class StageTaskSeeder
{
    /**
     * Rebuilds one stage_task_list field's checklist for a (new) design_plan_type, carrying
     * over matching tasks' progress — used when a reclassification must swap an
     * already-seeded Monitoring matrix for the correct type's one.
     */
    public static function reseedFor(ProjectStage $stage, string $fieldKey, ?string $type): void
    {
        ChecklistReseeder::reseed(
            $stage->stageTasks()->where('field_key', $fieldKey),
            StageTaskDefinitions::forField($stage->stage, $fieldKey, $type),
            ['field_key' => $fieldKey],
        );
    }

    public static function seedMissingFor(ProjectStage $stage, ?string $type = null): void
    {
        $stageTaskFields = collect(ProjectStageFieldDefinitions::forStage($stage->stage))
            ->where('type', 'stage_task_list');

        foreach ($stageTaskFields as $field) {
            if ($stage->stageTasks()->where('field_key', $field['key'])->exists()) {
                continue;
            }

            foreach (StageTaskDefinitions::forField($stage->stage, $field['key'], $type) as $index => $task) {
                $stage->stageTasks()->create([
                    'field_key' => $field['key'],
                    'section' => $task['section'] ?? null,
                    'subsection' => $task['subsection'] ?? null,
                    'name' => $task['name'],
                    'weight' => $task['weight'],
                    'status' => IspTaskStatus::Pending,
                    'status_changed_at' => now(),
                    'sort_order' => $index,
                ]);
            }
        }
    }
}
