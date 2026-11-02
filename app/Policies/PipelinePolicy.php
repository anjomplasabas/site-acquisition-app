<?php

namespace App\Policies;

use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\SitePlanningActionPermission;
use App\Models\Pipeline;
use App\Models\PipelineApproval;
use App\Models\User;

class PipelinePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->name === 'SUPER_ADMIN' ? true : null;
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        if ($this->hasAction($user, PipelineActionPermission::ViewAll)) {
            return true;
        }

        if (
            $this->hasAction($user, PipelineActionPermission::ViewAssignedSaq)
            && $pipeline->assigned_saq_id === $user->id
        ) {
            return true;
        }

        if (
            ($user->role?->hasActionPermission(SitePlanningActionPermission::FillSurvey->value) ?? false)
            && $pipeline->siteSurvey?->surveyor_id === $user->id
        ) {
            return true;
        }

        if (
            $this->hasAction($user, PipelineActionPermission::HandleCommercials)
            && in_array($pipeline->status, [PipelineStatus::Commercials, PipelineStatus::Approval], true)
        ) {
            return true;
        }

        if ($this->canViewApprovalStage($user, $pipeline)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return ($user->role?->hasPermissionFor(PipelineStatus::Prospect) ?? false)
            || ($user->role?->hasPermissionFor(PipelineStatus::Prospecting) ?? false);
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return $pipeline->project_manager_id === $user->id
            && in_array($pipeline->status, [PipelineStatus::Prospect, PipelineStatus::Prospecting], true)
            && ($user->role?->hasPermissionFor($pipeline->status) ?? false);
    }

    public function startAcquisition(User $user, Pipeline $pipeline): bool
    {
        return $pipeline->project_manager_id === $user->id
            && ($user->role?->hasPermissionFor(PipelineStatus::Prospecting) ?? false);
    }

    public function assignSaq(User $user, Pipeline $pipeline): bool
    {
        return in_array($pipeline->status, [PipelineStatus::Prospect, PipelineStatus::Prospecting], true)
            && $this->hasAction($user, PipelineActionPermission::AssignSaq);
    }

    public function moveToSiteSurveyPending(User $user, Pipeline $pipeline): bool
    {
        return $pipeline->status === PipelineStatus::Prospecting
            && $pipeline->assigned_saq_id === $user->id
            && $this->hasAction($user, PipelineActionPermission::MoveToSiteSurveyPending)
            && ($user->role?->hasPermissionFor(PipelineStatus::Prospecting) ?? false);
    }

    /**
     * Only the Prospecting → Site Survey Pending hand-off stays here — scheduling the survey
     * and filling it in are Site Planning module concerns now (see SitePlanningPolicy).
     */
    public function manageSiteVisit(User $user, Pipeline $pipeline): bool
    {
        return $this->moveToSiteSurveyPending($user, $pipeline);
    }

    public function editSurveyFindings(User $user, Pipeline $pipeline): bool
    {
        if (! in_array($pipeline->status, [PipelineStatus::Prospect, PipelineStatus::Prospecting], true)) {
            // Once in Site Survey Pending/Site Survey and beyond, Survey Findings is a Site
            // Planning module concern (see SitePlanningPolicy) and stays read-only here.
            return false;
        }

        return ($user->role?->hasPermissionFor($pipeline->status) ?? false)
            && $pipeline->assigned_saq_id === $user->id
            && $this->hasAction($user, PipelineActionPermission::ViewAssignedSaq);
    }

    public function handleCommercials(User $user, Pipeline $pipeline): bool
    {
        return $this->hasAction($user, PipelineActionPermission::HandleCommercials)
            && ($user->role?->hasPermissionFor($pipeline->status) ?? false);
    }

    public function deleteSurveyDocument(User $user, Pipeline $pipeline): bool
    {
        return $this->manageSiteVisit($user, $pipeline);
    }

    public function decideApproval(User $user, Pipeline $pipeline, PipelineApproval $approval): bool
    {
        return $user->role_id === $approval->role_id
            && $this->hasAction($user, PipelineActionPermission::DecideApproval);
    }

    public function markActive(User $user, Pipeline $pipeline): bool
    {
        return $user->role?->hasPermissionFor(PipelineStatus::ReadyForDeployment) ?? false;
    }

    public function markInactive(User $user, Pipeline $pipeline): bool
    {
        return $this->hasAction($user, PipelineActionPermission::MarkInactive)
            && ($user->role?->hasPermissionFor($pipeline->status) ?? false)
            && $user->can('view', $pipeline);
    }

    public function markCompleted(User $user, Pipeline $pipeline): bool
    {
        return false; // Super Admin only via before()
    }

    private function hasAction(User $user, PipelineActionPermission $permission): bool
    {
        return $user->role?->hasActionPermission($permission->value) ?? false;
    }

    private function canViewApprovalStage(User $user, Pipeline $pipeline): bool
    {
        return $pipeline->status === PipelineStatus::Approval
            && $this->hasAction($user, PipelineActionPermission::DecideApproval)
            && ($user->role?->hasPermissionFor(PipelineStatus::Approval) ?? false);
    }
}
