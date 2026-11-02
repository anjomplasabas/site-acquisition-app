<?php

namespace App\Support;

use App\Enums\IspTaskStatus;
use App\Models\SitePlanningAssignment;

/**
 * Seeds an assignment's Plans/Drawing checklist (SitePlanningTaskDefinitions::tasks()) the first
 * time a Type is chosen (see SitePlanningController::setDesignPlanType) — idempotent, and a no-op
 * if design_plan_type isn't set yet, mirroring StageTaskSeeder's null-safety.
 */
class SitePlanningTaskSeeder
{
    /**
     * Rebuilds the checklist for the assignment's (new) design_plan_type, carrying over
     * matching tasks' progress — used when an Infra Manager reclassifies the type.
     */
    public static function reseedFor(SitePlanningAssignment $assignment): void
    {
        if ($assignment->design_plan_type === null) {
            return;
        }

        ChecklistReseeder::reseed(
            $assignment->sitePlanningTasks(),
            SitePlanningTaskDefinitions::tasks($assignment->design_plan_type),
        );
    }

    public static function seedMissingFor(SitePlanningAssignment $assignment): void
    {
        if ($assignment->design_plan_type === null || $assignment->sitePlanningTasks()->exists()) {
            return;
        }

        foreach (SitePlanningTaskDefinitions::tasks($assignment->design_plan_type) as $index => $task) {
            $assignment->sitePlanningTasks()->create([
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
