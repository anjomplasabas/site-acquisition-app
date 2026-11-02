<?php

namespace App\Support;

use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PendingPipelineActions
{
    /**
     * @param  Builder<Pipeline>  $visiblePipelines
     * @param  array{yellow_days: int, red_days: int}  $thresholds
     * @return Collection<int, array<string, mixed>>
     */
    public function forVisiblePipelines(Builder $visiblePipelines, array $thresholds): Collection
    {
        $stageContext = app(PipelineStageContext::class);

        $pipelines = (clone $visiblePipelines)
            ->with([
                'projectManager:id,full_name',
                'assignedSaq:id,full_name',
                'siteSurvey.surveyor:id,full_name',
                'approvals.role:id,name',
            ])
            ->whereNotIn('status', [PipelineStatus::Inactive->value, PipelineStatus::Completed->value])
            ->latest('last_activity_at')
            ->limit(100)
            ->get();

        return $pipelines
            ->flatMap(function (Pipeline $pipeline) use ($stageContext, $thresholds): array {
                $context = $stageContext->forPipeline($pipeline, $thresholds);
                $pipeline->setAttribute('stage_context', $context);

                return $this->actionRows($pipeline, $context);
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function actionRows(Pipeline $pipeline, array $context): array
    {
        if ($pipeline->status !== PipelineStatus::SiteSurvey) {
            return [
                $this->row(
                    $pipeline,
                    $context,
                    $this->actionKey($pipeline),
                    $context['owner_label'],
                    $this->actionLabel($pipeline),
                ),
            ];
        }

        $rows = [];

        if ($pipeline->siteSurvey?->survey_completed_at === null) {
            $rows[] = $this->row(
                $pipeline,
                $context,
                'complete_site_survey',
                $pipeline->siteSurvey?->surveyor?->full_name ?? 'Assigned Surveyor',
                'Complete Site Survey',
                'surveyor',
            );
        }

        if ($pipeline->siteSurvey?->network_feasibility_completed_at === null) {
            $rows[] = $this->row(
                $pipeline,
                $context,
                'complete_network_feasibility',
                'Network users',
                'Complete Network Feasibility',
                'network_users',
            );
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $context */
    private function row(Pipeline $pipeline, array $context, string $actionKey, string $ownerLabel, string $actionLabel, ?string $ownerType = null): array
    {
        return [
            'action_key' => $actionKey,
            'pipeline' => $pipeline,
            'stage' => $pipeline->status->value,
            'owner_label' => $ownerLabel,
            'owner_type' => $ownerType ?? $context['owner_type'],
            'action_label' => $actionLabel,
            'stage_context' => $context,
        ];
    }

    private function actionKey(Pipeline $pipeline): string
    {
        return match ($pipeline->status) {
            PipelineStatus::Prospect => $pipeline->assigned_saq_id === null ? 'assign_saq' : 'start_prospecting',
            PipelineStatus::Prospecting => 'move_to_site_survey_pending',
            PipelineStatus::SiteSurveyPending => 'schedule_site_survey',
            PipelineStatus::Commercials => 'submit_commercials',
            PipelineStatus::Approval => 'decide_approval',
            PipelineStatus::ReadyForDeployment => 'mark_ready_for_service',
            default => 'none',
        };
    }

    private function actionLabel(Pipeline $pipeline): string
    {
        return match ($pipeline->status) {
            PipelineStatus::Prospect => $pipeline->assigned_saq_id === null ? 'Assign SAQ Specialist' : 'Start prospecting',
            PipelineStatus::Prospecting => 'Move to Site Survey Pending',
            PipelineStatus::SiteSurveyPending => 'Schedule Site Survey',
            PipelineStatus::Commercials => 'Submit Commercials',
            PipelineStatus::Approval => 'Decide Approval',
            PipelineStatus::ReadyForDeployment => 'Mark Ready for Service',
            default => 'No pending action',
        };
    }

    /** @return Collection<int, User> */
    public function usersForAction(PipelineActionPermission $permission): Collection
    {
        return User::query()
            ->whereHas('role.actionPermissions', fn (Builder $query) => $query->where('permission', $permission->value))
            ->select('id', 'full_name', 'email')
            ->orderBy('full_name')
            ->get();
    }
}
