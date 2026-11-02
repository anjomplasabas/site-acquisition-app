<?php

namespace App\Models;

use App\Enums\PipelineStatus;
use App\Enums\ProjectStage;
use App\Enums\SidebarModule;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
#[Table(timestamps: false)]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function modulePermissions(): HasMany
    {
        return $this->hasMany(RoleModulePermission::class);
    }

    public function actionPermissions(): HasMany
    {
        return $this->hasMany(RoleActionPermission::class);
    }

    public function projectStagePermissions(): HasMany
    {
        return $this->hasMany(RoleProjectStagePermission::class);
    }

    public function hasPermissionFor(PipelineStatus $status): bool
    {
        return $this->permissions->contains('pipeline_status', $status->value);
    }

    public function hasProjectStagePermission(ProjectStage $stage): bool
    {
        if ($this->name === 'SUPER_ADMIN') {
            return true;
        }

        return $this->projectStagePermissions->contains('project_stage', $stage->value);
    }

    public function hasActionPermission(string $permission): bool
    {
        if ($this->name === 'SUPER_ADMIN') {
            return true;
        }

        return $this->actionPermissions->contains('permission', $permission);
    }

    public function canAccessModule(SidebarModule|string $module): bool
    {
        if ($this->name === 'SUPER_ADMIN') {
            return true;
        }

        $value = $module instanceof SidebarModule ? $module->value : $module;

        return $this->modulePermissions->contains('module', $value);
    }
}
