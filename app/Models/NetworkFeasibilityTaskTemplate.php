<?php

namespace App\Models;

use App\Enums\ProjectStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The template data behind the Network module's JO (Job Order) checklist
 * (App\Support\NetworkFeasibilityTaskDefinitions) — not yet admin-editable (no CRUD UI exists),
 * but reading from here rather than a hardcoded PHP array lays the groundwork for that. One flat
 * list, no design_plan_type variance.
 */
#[Fillable(['name', 'stage', 'requires_document', 'sort_order', 'is_active'])]
class NetworkFeasibilityTaskTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'stage' => ProjectStage::class,
            'requires_document' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
