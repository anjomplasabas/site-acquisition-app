<?php

namespace App\Support;

use App\Enums\TaskTemplateContext;
use App\Models\TaskTemplate;

/**
 * The Plans/Drawing checklist worked by the Infra Designer role during Site Planning —
 * Appendix 2.a/2.a.i "Design Planning Status Matrix". `Initial Draft` (15%) and `Final Checking`
 * (15%) are fixed and identical across every Type; only the middle "In Progress Design Plan"
 * section (70%) varies by design_plan_type (Appendix 2.a for FIBER, 2.a.i for the other three).
 * Leaf weights are already expressed as an absolute % of the whole (not a % of their section), so
 * they sum straight to 100 without any section-weight math. `section`/`subsection` are purely for
 * grouped display (PlansDrawingChecklist.vue); they carry no weight of their own.
 *
 * Sourced from the `task_templates` table (see TaskTemplate) rather than hardcoded here — not yet
 * admin-editable (no CRUD UI exists), but this is the read path a future settings screen would
 * plug into. `design_plan_type IS NULL` rows are common to every type (Initial Draft/Final
 * Checking); rows with it set apply only to that type.
 */
class SitePlanningTaskDefinitions
{
    /** @return array<int, array{name: string, weight: int|float, section?: string, subsection?: string}> */
    public static function tasks(string $type): array
    {
        return TaskTemplate::query()
            ->where('context', TaskTemplateContext::SitePlanning)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('design_plan_type')->orWhere('design_plan_type', $type))
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
}
