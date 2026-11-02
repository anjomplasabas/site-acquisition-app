<?php

namespace App\Support;

use App\Enums\ApprovalStatus;
use App\Enums\PipelineStatus;
use App\Models\Pipeline;
use Carbon\CarbonInterface;

class PipelineStageContext
{
    /**
     * @param  array{yellow_days: int, red_days: int}  $thresholds
     * @return array{
     *     stage: string,
     *     stage_since: string|null,
     *     stage_age_days: int|null,
     *     stage_age_label: string|null,
     *     owner_type: string,
     *     owner_name: string|null,
     *     owner_label: string,
     *     level: string|null,
     *     classes: string|null
     * }
     */
    public function forPipeline(Pipeline $pipeline, array $thresholds): array
    {
        $stageSince = $this->stageSince($pipeline);
        $days = $stageSince === null
            ? null
            : max(0, (int) floor($stageSince->diffInHours(now()) / 24));
        $owner = $this->owner($pipeline);
        $level = $days === null ? null : $this->level($days, $thresholds);

        return [
            'stage' => $pipeline->status->value,
            'stage_since' => $stageSince?->toJSON(),
            'stage_age_days' => $days,
            'stage_age_label' => $days === null ? null : "{$days}d in stage",
            'owner_type' => $owner['type'],
            'owner_name' => $owner['name'],
            'owner_label' => $owner['label'],
            'level' => $level,
            'classes' => $level === null ? null : $this->classes($level),
        ];
    }

    private function stageSince(Pipeline $pipeline): ?CarbonInterface
    {
        if ($pipeline->status_changed_at !== null) {
            return $pipeline->status_changed_at;
        }

        $status = $pipeline->status->value;

        $statusLog = $pipeline->relationLoaded('statusLogs')
            ? $pipeline->statusLogs
                ->where('status', $status)
                ->sortByDesc('created_at')
                ->first()
            : $pipeline->statusLogs()
                ->where('status', $status)
                ->latest('created_at')
                ->first();

        return $statusLog?->created_at ?? $pipeline->created_at;
    }

    /** @return array{type: string, name: string|null, label: string} */
    private function owner(Pipeline $pipeline): array
    {
        if ($pipeline->status === PipelineStatus::Approval) {
            $pendingRole = $this->pendingApprovalRole($pipeline);

            return $this->ownerData('approval_role', $pendingRole, $pendingRole ?? 'Approver');
        }

        return match ($pipeline->status) {
            PipelineStatus::Prospect => $this->ownerData(
                'lead_saq',
                $pipeline->assignedSaq?->full_name,
                $pipeline->assignedSaq?->full_name ?? 'Lead SAQ / SAQ assignment',
            ),
            PipelineStatus::Prospecting => $this->ownerData(
                'saq_specialist',
                $pipeline->assignedSaq?->full_name,
                $pipeline->assignedSaq?->full_name ?? 'Assigned SAQ',
            ),
            PipelineStatus::SiteSurveyPending => $this->ownerData('infrastructure_manager', null, 'Infrastructure Manager'),
            // Once survey findings are done, ownership moves from the Surveyor to whoever is
            // working the Design Plan checklist — previously this stayed pinned to "Surveyor"
            // for the entire Design Planning phase. Network Feasibility runs in parallel
            // during this same pipeline status but isn't reflected here, same as this field
            // never having a distinct "Network Feasibility" owner type elsewhere.
            PipelineStatus::SiteSurvey => match (true) {
                $pipeline->siteSurvey?->survey_completed_at === null => $this->ownerData(
                    'surveyor',
                    $pipeline->siteSurvey?->surveyor?->full_name,
                    $pipeline->siteSurvey?->surveyor?->full_name ?? 'Assigned Surveyor',
                ),
                default => $this->ownerData(
                    'design_plan_assignee',
                    $pipeline->sitePlanningAssignment?->designPlanAssignee?->full_name,
                    $pipeline->sitePlanningAssignment?->designPlanAssignee?->full_name ?? 'Infra Designer',
                ),
            },
            PipelineStatus::Commercials => $this->ownerData('commercial_manager', null, 'Commercial Manager'),
            PipelineStatus::ReadyForDeployment => $this->ownerData('rfd_handler', null, 'RFD Handler'),
            PipelineStatus::ReadyForService => $this->ownerData('site_management', null, 'Site Management'),
            default => $this->ownerData('none', null, 'No pending owner'),
        };
    }

    /** @return array{type: string, name: string|null, label: string} */
    private function ownerData(string $type, ?string $name, string $label): array
    {
        return [
            'type' => $type,
            'name' => $name,
            'label' => $label,
        ];
    }

    private function pendingApprovalRole(Pipeline $pipeline): ?string
    {
        return $pipeline->approvals
            ->where('status', ApprovalStatus::Pending)
            ->sortBy('step')
            ->first()
            ?->role
            ?->name;
    }

    /** @param  array{yellow_days: int, red_days: int}  $thresholds */
    private function level(int $days, array $thresholds): string
    {
        if ($days >= $thresholds['red_days']) {
            return 'red';
        }

        if ($days >= $thresholds['yellow_days']) {
            return 'yellow';
        }

        return 'green';
    }

    private function classes(string $level): string
    {
        return match ($level) {
            'red' => 'bg-red-50 text-red-700 border border-red-200',
            'yellow' => 'bg-amber-50 text-amber-700 border border-amber-200',
            default => 'bg-green-50 text-green-700 border border-green-200',
        };
    }
}
