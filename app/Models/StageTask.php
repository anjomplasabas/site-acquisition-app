<?php

namespace App\Models;

use App\Enums\IspTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['project_stage_id', 'field_key', 'section', 'subsection', 'name', 'status', 'status_changed_at', 'weight', 'sort_order', 'notes'])]
class StageTask extends Model
{
    protected function casts(): array
    {
        return [
            'status' => IspTaskStatus::class,
            'status_changed_at' => 'datetime',
            'weight' => 'decimal:2',
        ];
    }

    public function projectStage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'stage_task_documents')
            ->withPivot('created_at');
    }
}
