<?php

namespace App\Models;

use App\Enums\IspTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['project_stage_isp_id', 'name', 'status', 'status_changed_at', 'sort_order'])]
class IspTask extends Model
{
    protected function casts(): array
    {
        return [
            'status' => IspTaskStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    public function projectStageIsp(): BelongsTo
    {
        return $this->belongsTo(ProjectStageIsp::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'isp_task_documents')
            ->withPivot('created_at');
    }
}
