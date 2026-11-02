<?php

namespace App\Policies;

use App\Enums\PipelineStatus;
use App\Enums\SitePlanningActionPermission;
use App\Models\SitePlanningAssignment;
use App\Models\User;

class SitePlanningPolicy
{
    /**
     * Scheduling/rescheduling is only allowed while the assignment's underlying pipeline is
     * still in one of these two statuses — the assignment record itself persists indefinitely
     * as an audit trail, mirroring NetworkPolicy's EDITABLE_STATUSES.
     */
    private const EDITABLE_STATUSES = [PipelineStatus::SiteSurveyPending, PipelineStatus::SiteSurvey];

    public function before(User $user, string $ability): ?bool
    {
        return $user->role?->name === 'SUPER_ADMIN' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAction($user, SitePlanningActionPermission::ListAssignments)
            || $this->hasAction($user, SitePlanningActionPermission::Manage)
            || $this->hasAction($user, SitePlanningActionPermission::FillSurvey)
            || $this->hasAction($user, SitePlanningActionPermission::DesignPlan)
            || $this->hasAction($user, SitePlanningActionPermission::ApproveDesignPlan);
    }

    public function view(User $user, SitePlanningAssignment $assignment): bool
    {
        if ($this->isDesignerOnly($user)) {
            return $assignment->design_plan_assignee_id === $user->id;
        }

        return $this->viewAny($user);
    }

    /**
     * A user who can only work design plans (no Manage/Approve/Surveyor/Assign duties) sees just
     * the design plans assigned to them — managers and surveyors keep seeing every record.
     */
    public function isDesignerOnly(User $user): bool
    {
        if ($user->role?->name === 'SUPER_ADMIN') {
            return false;
        }

        return $this->hasAction($user, SitePlanningActionPermission::DesignPlan)
            && ! $this->hasAction($user, SitePlanningActionPermission::Manage)
            && ! $this->hasAction($user, SitePlanningActionPermission::ApproveDesignPlan)
            && ! $this->hasAction($user, SitePlanningActionPermission::AssignSiteSurveyor)
            && ! $this->hasAction($user, SitePlanningActionPermission::FillSurvey);
    }

    /**
     * Documents on the assignment record only — scheduling itself moved to its own
     * assignSiteSurveyor() ability below, decoupled from the broader Manage permission.
     */
    public function manageAssignment(User $user, SitePlanningAssignment $assignment): bool
    {
        return in_array($assignment->pipeline->status, self::EDITABLE_STATUSES, true)
            && $this->hasAction($user, SitePlanningActionPermission::Manage);
    }

    /**
     * Assigning/scheduling the Surveyor is its own permission, distinct from the broader Manage
     * permission — today held only by Infrastructure Manager (Specialist intentionally excluded,
     * mirroring approveDesignPlan()'s Manager-only tier), but independently grantable to any
     * future custom role.
     */
    public function assignSiteSurveyor(User $user, SitePlanningAssignment $assignment): bool
    {
        return in_array($assignment->pipeline->status, self::EDITABLE_STATUSES, true)
            && $this->hasAction($user, SitePlanningActionPermission::AssignSiteSurveyor);
    }

    /**
     * The inspection form stays locked to the specifically assigned Surveyor, not any user
     * holding the Surveyor role — mirrors PipelinePolicy::fillSurvey()'s individual scoping.
     */
    public function fillSurvey(User $user, SitePlanningAssignment $assignment): bool
    {
        return $assignment->pipeline->status === PipelineStatus::SiteSurvey
            && $this->hasAction($user, SitePlanningActionPermission::FillSurvey)
            && $assignment->siteSurvey?->surveyor_id === $user->id;
    }

    /**
     * The assigned Surveyor, or whoever schedules the survey (Infrastructure Manager), may correct
     * the site's Type while the survey is under way — e.g. Familia → Residential when the initially
     * selected type doesn't suit the client's location.
     */
    public function updateProjectType(User $user, SitePlanningAssignment $assignment): bool
    {
        return $this->fillSurvey($user, $assignment) || $this->assignSiteSurveyor($user, $assignment);
    }

    public function submitInspection(User $user, SitePlanningAssignment $assignment): bool
    {
        return $this->fillSurvey($user, $assignment);
    }

    /**
     * The assigned Surveyor needs to replace/remove their own Site Survey photos and documents
     * while filling out the inspection form, not just whoever scheduled the survey — but this
     * stays separate from manageAssignment() so a Surveyor still can't reschedule/reassign.
     */
    public function manageDocuments(User $user, SitePlanningAssignment $assignment): bool
    {
        return $this->manageAssignment($user, $assignment) || $this->fillSurvey($user, $assignment);
    }

    /**
     * Once a specific Infra Designer has been assigned (see assignDesignPlan()), the checklist
     * locks to that assignee — mirroring fillSurvey()'s individual scoping — with Manage holders
     * (Infrastructure Manager/Specialist) kept as a standing fallback for oversight. Before an
     * assignee is set, only Manage holders can work it. Not gated by pipeline status like
     * manageAssignment()'s EDITABLE_STATUSES, since this work should remain workable even across
     * a later revert/re-entry into Site Survey.
     */
    public function manageDesignPlan(User $user, SitePlanningAssignment $assignment): bool
    {
        if ($this->hasAction($user, SitePlanningActionPermission::Manage)) {
            return true;
        }

        // Until an assignee is set nobody but Manage holders owns the design plan — an
        // unassigned plan must not be workable by any Infra Designer.
        return $assignment->design_plan_assignee_id !== null
            && $assignment->design_plan_assignee_id === $user->id;
    }

    /**
     * Assigning who works the design plan is its own permission, distinct from Manage — today
     * held by Infrastructure Manager and Specialist, independently grantable to any future
     * custom role.
     */
    public function assignDesignPlan(User $user, SitePlanningAssignment $assignment): bool
    {
        return $this->hasAction($user, SitePlanningActionPermission::AssignDesignPlan);
    }

    public function completeDesignPlan(User $user, SitePlanningAssignment $assignment): bool
    {
        return $this->manageDesignPlan($user, $assignment);
    }

    /**
     * Deliberately not delegated to manageDesignPlan() — approval is a distinct, narrower
     * ability (Infrastructure Manager only) from the broader "who can work the checklist" set.
     * Self-approval (the same Manager account did both) is allowed by design.
     */
    public function approveDesignPlan(User $user, SitePlanningAssignment $assignment): bool
    {
        return $this->hasAction($user, SitePlanningActionPermission::ApproveDesignPlan);
    }

    private function hasAction(User $user, SitePlanningActionPermission $permission): bool
    {
        return $user->role?->hasActionPermission($permission->value) ?? false;
    }
}
