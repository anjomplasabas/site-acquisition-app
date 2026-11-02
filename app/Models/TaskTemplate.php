<?php

namespace App\Models;

use App\Enums\TaskTemplateContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The template data behind Site Planning's Plans/Drawing checklist and Monitoring's
 * Infrastructure Deployment Status checklist (App\Support\SitePlanningTaskDefinitions,
 * App\Support\StageTaskDefinitions) — not yet admin-editable (no CRUD UI exists), but reading
 * from here rather than hardcoded PHP arrays lays the groundwork for that. `design_plan_type`
 * null means the row is common to every type within its `context` (used by Site Planning's
 * Initial Draft/Final Checking sections; Monitoring has no shared rows).
 */
#[Fillable(['context', 'design_plan_type', 'section', 'subsection', 'name', 'weight', 'sort_order', 'is_active'])]
class TaskTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'context' => TaskTemplateContext::class,
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
