<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Database\Factories\PipelineApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pipeline_id', 'step', 'role_id', 'decided_by_id', 'status', 'decided_at', 'remarks'])]
#[Table(timestamps: false)]
class PipelineApproval extends Model
{
    /** @use HasFactory<PipelineApprovalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }
}
