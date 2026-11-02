<?php

namespace App\Models;

use App\Enums\ProjectStage as ProjectStageEnum;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['pipeline_id', 'tower_id', 'status', 'current_stage', 'remarks', 'started_at', 'completed_at'])]
class Project extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'current_stage' => ProjectStageEnum::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function tower(): BelongsTo
    {
        return $this->belongsTo(Tower::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class)->orderBy('order');
    }

    public function getProgressAttribute(): array
    {
        $total = $this->stages->count();
        $done = $this->stages->where('status', 'Completed')->count();

        return ['done' => $done, 'total' => $total];
    }
}
