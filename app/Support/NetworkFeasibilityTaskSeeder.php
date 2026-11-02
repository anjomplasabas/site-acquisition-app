<?php

namespace App\Support;

use App\Enums\IspTaskStatus;
use App\Models\NetworkFeasibility;

/**
 * Seeds a Network Site ISP's fixed JO (Job Order) checklist (NetworkFeasibilityTaskDefinitions)
 * idempotently — a no-op once the feasibility already has tasks. Used by two independent
 * triggers: InitializeProject::carryForwardIsps (when the pipeline already had MOA/NTP attached
 * at Commercials, so the checklist is seeded the moment the Project is created) and
 * ProjectStageController::uploadDocument (when MOA/NTP weren't present at Commercials, so the
 * checklist is deferred until both are uploaded directly on the Planning stage instead).
 */
class NetworkFeasibilityTaskSeeder
{
    public static function seedMissingFor(NetworkFeasibility $feasibility): void
    {
        if ($feasibility->tasks()->exists()) {
            return;
        }

        foreach (NetworkFeasibilityTaskDefinitions::all() as $index => $task) {
            $feasibility->tasks()->create([
                'name' => $task['name'],
                'project_stage' => $task['stage'],
                'requires_document' => $task['requires_document'],
                'status' => IspTaskStatus::Pending,
                'status_changed_at' => now(),
                'sort_order' => $index,
            ]);
        }
    }
}
