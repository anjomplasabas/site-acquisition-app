<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['role_id', 'pipeline_status'])]
#[Table(timestamps: false)]
class RolePermission extends Model
{
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
