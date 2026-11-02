<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['role_id', 'project_stage'])]
#[Table(timestamps: false)]
class RoleProjectStagePermission extends Model
{
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
