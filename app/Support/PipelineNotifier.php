<?php

namespace App\Support;

use App\Enums\NetworkActionPermission;
use App\Enums\NotificationType;
use App\Enums\PipelineActionPermission;
use App\Enums\ProjectActionPermission;
use App\Enums\SitePlanningActionPermission;
use App\Models\NetworkSiteAssignment;
use App\Models\Pipeline;
use App\Models\PipelineApproval;
use App\Models\SitePlanningAssignment;
use App\Models\User;
use App\Notifications\PipelineNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PipelineNotifier
{
    public function saqAssigned(Pipeline $pipeline, User $assignee): void
    {
        $this->send([$assignee], NotificationType::SaqAssigned, $pipeline, "You've been assigned as SAQ Specialist for {$pipeline->name}.");
    }

    public function siteSurveyPending(SitePlanningAssignment $assignment, User $actor): void
    {
        $recipients = $this->usersWithPermission(SitePlanningActionPermission::AssignSiteSurveyor->value, $actor);

        $this->send(
            $recipients,
            NotificationType::SiteSurveyPending,
            $assignment->pipeline,
            "{$assignment->pipeline->name} is ready for a surveyor to be assigned.",
            route('site-planning.show', $assignment)
        );
    }

    public function surveyorAssigned(SitePlanningAssignment $assignment, User $surveyor): void
    {
        $assignment->loadMissing(['siteSurvey', 'pipeline.siteProfile.address', 'tower']);

        $address = $assignment->pipeline->siteProfile?->address;
        $details = collect([
            $assignment->tower !== null ? "Tower: {$assignment->tower->name}" : null,
            $assignment->siteSurvey?->survey_date !== null ? "Survey date: {$assignment->siteSurvey->survey_date->format('M j, Y')}" : null,
            $address !== null ? 'Address: '.collect([$address->street, $address->barangay, $address->city, $address->province])->filter()->implode(', ') : null,
            filled($assignment->siteSurvey?->remarks) ? "Remarks: {$assignment->siteSurvey->remarks}" : null,
        ])->filter()->implode('. ');

        $this->send(
            [$surveyor],
            NotificationType::SurveyorAssigned,
            $assignment->pipeline,
            "You've been assigned to survey {$assignment->pipeline->name} ({$assignment->pipeline->pipeline_id})".($details !== '' ? ". {$details}." : '.'),
            route('site-planning.show', $assignment)
        );
    }

    public function designPlanAssigned(SitePlanningAssignment $assignment, User $designer): void
    {
        $this->send(
            [$designer],
            NotificationType::DesignPlanAssigned,
            $assignment->pipeline,
            "You've been assigned to draft the design plan for {$assignment->pipeline->name}.",
            route('site-planning.show', $assignment)
        );
    }

    /**
     * Fires once the Plans/Drawing checklist is seeded but no Infra Designer has been assigned
     * yet — mirrors siteSurveyPending()'s "needs an assignment" pattern, recipients being
     * whoever can actually perform the assignment (AssignDesignPlan holders).
     */
    public function designPlanAssignmentNeeded(SitePlanningAssignment $assignment, User $actor): void
    {
        $recipients = $this->usersWithPermission(SitePlanningActionPermission::AssignDesignPlan->value, $actor);

        $this->send(
            $recipients,
            NotificationType::DesignPlanAssignmentNeeded,
            $assignment->pipeline,
            "{$assignment->pipeline->name} is ready for a designer to be assigned.",
            route('site-planning.show', $assignment)
        );
    }

    public function networkAssignmentCreated(NetworkSiteAssignment $assignment, User $actor): void
    {
        $recipients = $this->usersWithPermission(NetworkActionPermission::ManageNetworkFeasibility->value, $actor);

        $this->send(
            $recipients,
            NotificationType::NetworkAssignmentCreated,
            $assignment->pipeline,
            "{$assignment->pipeline->name} is ready for Network Feasibility to begin.",
            route('network.show', $assignment)
        );
    }

    public function networkFeasibilityCompleted(NetworkSiteAssignment $assignment, User $actor): void
    {
        $recipients = $this->usersWithPermission(SitePlanningActionPermission::Manage->value, $actor);

        $this->send(
            $recipients,
            NotificationType::NetworkFeasibilityCompleted,
            $assignment->pipeline,
            "Network Feasibility for {$assignment->pipeline->name} is complete.",
            route('network.show', $assignment)
        );
    }

    public function readyForCommercials(Pipeline $pipeline, User $actor): void
    {
        $recipients = $this->usersWithPermission(PipelineActionPermission::HandleCommercials->value, $actor);

        $this->send($recipients, NotificationType::ReadyForCommercials, $pipeline, "{$pipeline->name} is ready for Commercials — site survey and network feasibility are both complete.");
    }

    /**
     * Everyone who has to revise the returned work hears about it, with the rejection reason
     *: Site Planning managers, every surveyor and design plan assignee on the site's
     * assignments, and the Network team (which owns the ISP feasibility to be re-confirmed).
     */
    public function approvalRejected(Pipeline $pipeline, User $actor, ?string $remarks = null): void
    {
        $assigneeIds = $pipeline->sitePlanningAssignments()->with('siteSurvey')->get()
            ->flatMap(fn (SitePlanningAssignment $assignment): array => [
                $assignment->design_plan_assignee_id,
                $assignment->siteSurvey?->surveyor_id,
            ])
            ->push($pipeline->siteSurvey?->surveyor_id)
            ->filter();

        $recipients = $this->usersWithPermission([
            SitePlanningActionPermission::Manage->value,
            NetworkActionPermission::ManageNetworkFeasibility->value,
        ], $actor)
            ->merge(User::whereIn('id', $assigneeIds)->where('id', '!=', $actor->id)->get())
            ->unique('id')
            ->values();

        $url = $pipeline->sitePlanningAssignment !== null
            ? route('site-planning.show', $pipeline->sitePlanningAssignment)
            : null;

        $this->send($recipients, NotificationType::ApprovalRejected, $pipeline, "{$pipeline->name}'s approval was rejected and returned to Site Survey.".($remarks !== null && $remarks !== '' ? " Reason: {$remarks}" : ''), $url);
    }

    public function projectCreated(Pipeline $pipeline): void
    {
        $recipients = $this->usersWithPermission(ProjectActionPermission::UpdateProjectStatus->value);

        // Links to the default project, or the first tower's project if the site has more than
        // one — a notification can only carry one URL, so towers beyond the first are reachable
        // from there via the pipeline/site detail page.
        $project = $pipeline->project ?? $pipeline->projects()->oldest('id')->first();
        $url = $project !== null ? route('projects.show', $project) : route('pipelines.show', $pipeline);

        $this->send($recipients, NotificationType::ProjectCreated, $pipeline, "{$pipeline->name} has been approved and is now a project.", $url);
    }

    public function designPlanPendingApproval(SitePlanningAssignment $assignment, User $actor): void
    {
        $recipients = $this->usersWithPermission(SitePlanningActionPermission::ApproveDesignPlan->value, $actor);

        $this->send(
            $recipients,
            NotificationType::DesignPlanPendingApproval,
            $assignment->pipeline,
            "{$assignment->pipeline->name}'s design plan is ready for your approval.",
            route('site-planning.show', $assignment)
        );
    }

    public function designPlanRevisionRequested(SitePlanningAssignment $assignment, User $actor, string $remarks): void
    {
        $recipients = User::whereKey($assignment->design_plan_assignee_id)->where('id', '!=', $actor->id)->get();

        $this->send(
            $recipients,
            NotificationType::DesignPlanRevisionRequested,
            $assignment->pipeline,
            "{$assignment->pipeline->name}'s design plan needs revision. Reason: {$remarks}",
            route('site-planning.show', $assignment)
        );
    }

    /**
     * Unlike the other broadcasts above, recipients here are already dynamic — sourced from
     * whichever role the Approval Config UI assigned to this step, not a hardcoded role name.
     */
    public function approvalPending(Pipeline $pipeline, PipelineApproval $approval, User $actor): void
    {
        $recipients = User::where('role_id', $approval->role_id)->get()
            ->reject(fn (User $user): bool => $user->id === $actor->id)
            ->values();

        $this->send($recipients, NotificationType::ApprovalPending, $pipeline, "{$pipeline->name} is awaiting your approval as {$approval->role->name}.");
    }

    /** @param  iterable<int, User>  $recipients */
    private function send(iterable $recipients, NotificationType $type, Pipeline $pipeline, string $message, ?string $url = null): void
    {
        $recipients = $recipients instanceof Collection ? $recipients : collect($recipients);

        if ($recipients->isEmpty()) {
            return;
        }

        $url ??= route('pipelines.show', $pipeline);

        Notification::send($recipients, new PipelineNotification($type, $pipeline, $message, $url));
    }

    /**
     * Resolves recipients from who currently holds the given action permission(s) — via the
     * Roles & Permissions module — rather than a hardcoded list of role names, so a custom role
     * granted the same permission is automatically included with no code change.
     *
     * @param  string|array<int, string>  $permissions
     * @return Collection<int, User>
     */
    private function usersWithPermission(string|array $permissions, ?User $actor = null): Collection
    {
        $users = User::withActionPermission($permissions)->get();

        if ($actor === null) {
            return $users;
        }

        return $users->reject(fn (User $user): bool => $user->id === $actor->id)->values();
    }
}
