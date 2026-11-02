<?php

namespace App\Enums;

enum SitePlanningActionPermission: string
{
    case ListAssignments = 'site_planning.list';
    case Manage = 'site_planning.manage';
    case AssignSiteSurveyor = 'site_planning.assign_site_surveyor';
    case FillSurvey = 'site_planning.fill_survey';
    case DesignPlan = 'site_planning.design_plan';
    case ApproveDesignPlan = 'site_planning.approve_design_plan';
    case AssignDesignPlan = 'site_planning.assign_design_plan';

    public function label(): string
    {
        return match ($this) {
            self::ListAssignments => 'List',
            self::Manage => 'Manage',
            self::AssignSiteSurveyor => 'Assign Site Surveyor',
            self::FillSurvey => 'Fill Site Survey',
            self::DesignPlan => 'Design Plan',
            self::ApproveDesignPlan => 'Approve Design Plan',
            self::AssignDesignPlan => 'Assign Design Plan',
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
