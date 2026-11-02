<?php

namespace App\Enums;

enum PipelineActionPermission: string
{
    case ViewAll = 'pipelines.view_all';
    case ViewAssignedSaq = 'pipelines.view_assigned_saq';
    case AssignSaq = 'pipelines.assign_saq';
    case MoveToSiteSurveyPending = 'pipelines.move_to_site_survey_pending';
    case MarkInactive = 'pipelines.mark_inactive';
    case HandleCommercials = 'pipelines.handle_commercials';
    case DecideApproval = 'pipelines.decide_approval';

    public function label(): string
    {
        return match ($this) {
            self::ViewAll => 'View all pipelines',
            self::ViewAssignedSaq => 'View assigned SAQ pipelines',
            self::AssignSaq => 'Assign SAQ Specialist',
            self::MoveToSiteSurveyPending => 'Move to Site Survey Pending',
            self::MarkInactive => 'Mark Inactive',
            self::HandleCommercials => 'Handle Commercials',
            self::DecideApproval => 'Decide Approval',
        };
    }

    /** @return array<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $permission): array => ['value' => $permission->value, 'label' => $permission->label()],
            self::cases(),
        );
    }
}
