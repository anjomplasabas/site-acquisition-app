<?php

namespace App\Models;

use App\Enums\IspTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['site_planning_assignment_id', 'section', 'subsection', 'name', 'status', 'status_changed_at', 'weight', 'sort_order', 'notes'])]
class SitePlanningTask extends Model
{
    protected function casts(): array
    {
        return [
            'status' => IspTaskStatus::class,
            'status_changed_at' => 'datetime',
            'weight' => 'decimal:2',
        ];
    }

    public function sitePlanningAssignment(): BelongsTo
    {
        return $this->belongsTo(SitePlanningAssignment::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'site_planning_task_documents')
            ->withPivot('created_at');
    }
}
