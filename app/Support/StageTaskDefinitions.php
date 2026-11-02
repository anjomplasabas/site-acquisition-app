<?php

namespace App\Support;

use App\Enums\ProjectStage;
use App\Enums\TaskTemplateContext;
use App\Models\TaskTemplate;

/**
 * The fixed, stage-level (not per-ISP) checklist behind a stage_task_list field (StageTask,
 * seeded once per field — see InitializeProject::seedStageTasks). Keyed by field, not just by
 * stage, since a stage could in principle carry more than one stage_task_list field.
 *
 * Monitoring's "Infrastructure Deployment Status" checklist is sourced from the `task_templates`
 * table (see TaskTemplate) rather than hardcoded here — not yet admin-editable (no CRUD UI
 * exists), but this is the read path a future settings screen would plug into. Three matrices
 * from "Proposed Infra Manual Annexes 1 to 4 v2_1.pptx.pdf" (pages 6-8), one per design_plan_type
 * (set during Site Planning, see SitePlanningAssignment::design_plan_type). Leaf weights are
 * already expressed as an absolute % of the whole (not a % of their section), so they sum
 * straight to 100 without any section-weight math. `section`/`subsection` are purely for grouped
 * display (StageTaskListField.vue); they carry no weight of their own.
 */
class StageTaskDefinitions
{
    /**
     * @return array<int, array{name: string, weight: int|float, section?: string, subsection?: string}>
     */
    public static function forField(ProjectStage $stage, string $fieldKey, ?string $type = null): array
    {
        if ($stage === ProjectStage::Monitoring && $fieldKey === 'infrastructure_deployment_status') {
            // No distinct Annex 2.b matrix exists for FIBER — reuse the UTP/COPPER-Residential
            // one, since Fiber's physical installation work is closest to it among the three.
            $resolvedType = $type === 'FIBER' ? 'Residential' : $type;

            if ($resolvedType === null) {
                return [];
            }

            return TaskTemplate::query()
                ->where('context', TaskTemplateContext::Monitoring)
                ->where('design_plan_type', $resolvedType)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (TaskTemplate $template): array => array_filter([
                    'section' => $template->section,
                    'subsection' => $template->subsection,
                    'name' => $template->name,
                    'weight' => (float) $template->weight,
                ], fn ($value): bool => $value !== null))
                ->all();
        }

        return [];
    }
}
