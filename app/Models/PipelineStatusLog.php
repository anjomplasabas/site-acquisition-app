<?php

namespace App\Models;

use App\Enums\PipelineStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pipeline_id', 'status', 'changed_by'])]
class PipelineStatusLog extends Model
{
    public static function record(Pipeline $pipeline, PipelineStatus $status): void
    {
        static::create([
            'pipeline_id' => $pipeline->id,
            'status' => $status->value,
            'changed_by' => auth()->id(),
        ]);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
