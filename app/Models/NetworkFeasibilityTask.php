<?php

namespace App\Models;

use App\Enums\IspTaskStatus;
use App\Enums\ProjectStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['network_feasibility_id', 'name', 'project_stage', 'status', 'status_changed_at', 'sort_order', 'requires_document', 'notes'])]
class NetworkFeasibilityTask extends Model
{
    protected function casts(): array
    {
        return [
            'project_stage' => ProjectStage::class,
            'status' => IspTaskStatus::class,
            'status_changed_at' => 'datetime',
            'requires_document' => 'boolean',
        ];
    }

    public function networkFeasibility(): BelongsTo
    {
        return $this->belongsTo(NetworkFeasibility::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'network_feasibility_task_documents')
            ->withPivot('created_at');
    }
}
