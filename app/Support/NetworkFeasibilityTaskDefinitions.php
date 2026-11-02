<?php

namespace App\Support;

use App\Enums\ProjectStage;
use App\Models\NetworkFeasibilityTaskTemplate;

/**
 * The JO (Job Order) checklist auto-created for every Network Site ISP once its pipeline is
 * approved and a Project exists (InitializeProject::carryForwardIsps). One continuous list per
 * ISP, spanning all three Project stages — only ever actioned from the Network module, never
 * from Project Management.
 *
 * Sourced from the `network_feasibility_task_templates` table (see NetworkFeasibilityTaskTemplate)
 * rather than hardcoded here — not yet admin-editable (no CRUD UI exists), but this is the read
 * path a future settings screen would plug into.
 */
class NetworkFeasibilityTaskDefinitions
{
    /** @return array<int, array{name: string, stage: ProjectStage, requires_document: bool}> */
    public static function all(): array
    {
        return NetworkFeasibilityTaskTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (NetworkFeasibilityTaskTemplate $template): array => [
                'name' => $template->name,
                'stage' => $template->stage,
                'requires_document' => $template->requires_document,
            ])
            ->all();
    }
}
