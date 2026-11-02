<?php

namespace App\Support;

use App\Enums\ProjectStage;

/**
 * The fixed installation-checklist template auto-created for every ISP added to a stage's
 * isp_task_list field (ProjectStageIspController::store). Fixed by business process per
 * stage, not admin-editable.
 */
class IspTaskDefinitions
{
    private const PLANNING_TASKS = [
        'For Survey',
        'For Job Order',
        'Waiting for Cabinet Installation',
        'DF Approval',
        'For Quotation',
        'DP Creation',
        'Activation',
        'Permitting',
        'Plan Creation',
    ];

    private const EXECUTION_TASKS = [
        'RTA Activity',
        'OSP Activity',
        'Cabling Activity',
    ];

    // Monitoring's "Network Deployment Status" is per-ISP but only needs a single Pending/
    // In-Progress/Done tag per ISP, not a full breakdown — see ProjectStageFieldDefinitions.
    private const MONITORING_TASKS = [
        'Network Deployment',
    ];

    /** @return array<int, string> */
    public static function forStage(ProjectStage $stage): array
    {
        return match ($stage) {
            ProjectStage::Planning => self::PLANNING_TASKS,
            ProjectStage::Execution => self::EXECUTION_TASKS,
            ProjectStage::Monitoring => self::MONITORING_TASKS,
            default => [],
        };
    }
}
