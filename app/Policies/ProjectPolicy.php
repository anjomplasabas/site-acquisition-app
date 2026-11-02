<?php

namespace App\Policies;

use App\Enums\ProjectActionPermission;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->name === 'SUPER_ADMIN' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAction($user, ProjectActionPermission::ViewProjects);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->hasAction($user, ProjectActionPermission::ViewProjects);
    }

    public function updateStatus(User $user, Project $project): bool
    {
        return $this->hasAction($user, ProjectActionPermission::UpdateProjectStatus);
    }

    /**
     * Can the user edit this stage's field values? Requires both the general
     * ManageStageFields action AND the role being permitted to act on this specific stage.
     */
    public function manageStage(User $user, Project $project, ProjectStage $stage): bool
    {
        if (in_array($project->status, [ProjectStatus::OnHold, ProjectStatus::Completed, ProjectStatus::Cancelled], true)) {
            return false;
        }

        return $this->hasAction($user, ProjectActionPermission::ManageStageFields)
            && ($user->role?->hasProjectStagePermission($stage) ?? false);
    }

    public function completeStage(User $user, Project $project, ProjectStage $stage): bool
    {
        return $this->manageStage($user, $project, $stage);
    }

    public function reopenStage(User $user, Project $project, ProjectStage $stage): bool
    {
        return $this->manageStage($user, $project, $stage);
    }

    private function hasAction(User $user, ProjectActionPermission $permission): bool
    {
        return $user->role?->hasActionPermission($permission->value) ?? false;
    }
}
