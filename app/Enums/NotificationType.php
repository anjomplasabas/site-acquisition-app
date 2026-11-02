<?php

namespace App\Enums;

enum NotificationType: string
{
    case SaqAssigned = 'saq_assigned';
    case SiteSurveyPending = 'site_survey_pending';
    case SurveyorAssigned = 'surveyor_assigned';
    case NetworkAssignmentCreated = 'network_assignment_created';
    case ProjectCreated = 'project_created';
    case ApprovalPending = 'approval_pending';
    case DesignPlanPendingApproval = 'design_plan_pending_approval';
    case DesignPlanAssigned = 'design_plan_assigned';
    case NetworkFeasibilityCompleted = 'network_feasibility_completed';
    case ReadyForCommercials = 'ready_for_commercials';
    case ApprovalRejected = 'approval_rejected';
    case DesignPlanAssignmentNeeded = 'design_plan_assignment_needed';
    case DesignPlanRevisionRequested = 'design_plan_revision_requested';

    public function label(): string
    {
        return match ($this) {
            self::SaqAssigned => 'SAQ Specialist Assigned',
            self::SiteSurveyPending => 'Site Survey Needed',
            self::SurveyorAssigned => 'Site Survey Assigned',
            self::NetworkAssignmentCreated => 'Network Site Assignment',
            self::ProjectCreated => 'Project Created',
            self::ApprovalPending => 'Approval Needed',
            self::DesignPlanPendingApproval => 'Design Plan Pending Approval',
            self::DesignPlanAssigned => 'Design Plan Assigned',
            self::NetworkFeasibilityCompleted => 'Network Feasibility Completed',
            self::ReadyForCommercials => 'Ready for Commercials',
            self::ApprovalRejected => 'Approval Rejected',
            self::DesignPlanAssignmentNeeded => 'Design Plan Assignment Needed',
            self::DesignPlanRevisionRequested => 'Design Plan Revision Requested',
        };
    }
}
