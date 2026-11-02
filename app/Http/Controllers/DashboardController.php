<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Enums\ProjectStatus;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\SitePlanningAssignment;
use App\Support\PipelineActivity;
use App\Support\PipelineStageContext;
use App\Support\PipelineVisibility;
use App\Support\WorkflowSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Request $request): Response
    {
        $terminalStatuses = [
            PipelineStatus::Prospect->value,
            PipelineStatus::ReadyForService->value,
            PipelineStatus::Inactive->value,
            PipelineStatus::Completed->value,
        ];

        $thresholds = app(WorkflowSettings::class)->alarmThresholds();
        $activity = app(PipelineActivity::class);
        $stageContext = app(PipelineStageContext::class);

        $visiblePipelines = Pipeline::query()
            ->tap(fn ($query) => PipelineVisibility::apply($query, $request->user()));

        // ── Pipeline KPIs (single grouped query) ─────────────────────────────
        $statusCounts = (clone $visiblePipelines)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalLeads = (int) $statusCounts->sum();
        $inAcquisition = (int) $statusCounts->filter(fn ($_, $status) => ! in_array($status, $terminalStatuses))->sum();
        $pendingApproval = (int) $statusCounts->get(PipelineStatus::Approval->value, 0);
        $readyForService = (int) $statusCounts->get(PipelineStatus::ReadyForService->value, 0);

        // ── Design Plans completed and waiting for the Infra Manager's approval ─────────
        $designPlanForApproval = SitePlanningAssignment::query()
            ->whereHas('pipeline', fn ($query) => PipelineVisibility::apply($query, $request->user()))
            ->whereNotNull('design_plan_completed_at')
            ->whereNull('design_plan_approved_at')
            ->count();

        // ── Project KPIs ──────────────────────────────────────────────────────
        $projectStatusCounts = Project::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $activeProjectCount = (int) $projectStatusCounts->get(ProjectStatus::Active->value, 0);

        // ── Active projects health list (stage completion, not tasks) ──────────
        $activeProjects = Project::query()
            ->with(['pipeline:id,pipeline_id,name', 'stages'])
            ->where('status', ProjectStatus::Active->value)
            ->latest('started_at')
            ->limit(6)
            ->get()
            ->map(function (Project $project): array {
                $totalStages = $project->stages->count();
                $doneStages = $project->stages->where('status', 'Completed')->count();

                return [
                    'id' => $project->id,
                    'pipeline_id' => $project->pipeline->pipeline_id,
                    'pipeline_db_id' => $project->pipeline->id,
                    'name' => $project->pipeline->name,
                    'current_stage' => $project->current_stage->label(),
                    'done_stages' => $doneStages,
                    'total_stages' => $totalStages,
                    'progress_pct' => $totalStages > 0 ? round($doneStages / $totalStages * 100) : 0,
                    'started_at' => $project->started_at,
                ];
            });

        // ── Pipeline status distribution ──────────────────────────────────────
        $dashboardStatuses = [
            PipelineStatus::Prospect,
            PipelineStatus::Prospecting,
            PipelineStatus::SiteSurveyPending,
            PipelineStatus::SiteSurvey,
            PipelineStatus::Commercials,
            PipelineStatus::Approval,
            PipelineStatus::Completed,
        ];

        $statusDistribution = collect($dashboardStatuses)
            ->map(function (PipelineStatus $status) use ($statusCounts, $totalLeads): array {
                $count = (int) ($statusCounts[$status->value] ?? 0);

                return [
                    'status' => $status->value,
                    'count' => $count,
                    'percentage' => $totalLeads > 0 ? round(($count / $totalLeads) * 100) : 0,
                ];
            })
            ->values();

        // ── Stale leads needing attention ─────────────────────────────────────
        $attentionItems = (clone $visiblePipelines)
            ->with(['assignedSaq:id,full_name'])
            ->whereNotIn('status', [PipelineStatus::Inactive->value, PipelineStatus::Completed->value])
            ->where('status_changed_at', '<=', now()->subDays(7))
            ->oldest('status_changed_at')
            ->limit(5)
            ->get()
            ->map(fn (Pipeline $pipeline): array => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'pipeline_id' => $pipeline->pipeline_id,
                'status' => $pipeline->status->value,
                'status_changed_at' => $pipeline->status_changed_at,
                'assigned_saq' => $pipeline->assignedSaq?->full_name,
            ]);

        // ── Site aging overview ───────────────────────────────────────────────
        $agingOverview = (clone $visiblePipelines)
            ->with([
                'assignedSaq:id,full_name',
                'siteSurvey.surveyor:id,full_name',
                'approvals.role:id,name',
                'sitePlanningAssignment.designPlanAssignee:id,full_name',
            ])
            ->whereNotIn('status', [PipelineStatus::Inactive->value, PipelineStatus::Completed->value])
            ->oldest('last_activity_at')
            ->oldest('status_changed_at')
            ->limit(6)
            ->get()
            ->map(function (Pipeline $pipeline) use ($activity, $stageContext, $thresholds): array {
                $stage = $stageContext->forPipeline($pipeline, $thresholds);

                return [
                    'id' => $pipeline->id,
                    'name' => $pipeline->name,
                    'pipeline_id' => $pipeline->pipeline_id,
                    'status' => $pipeline->status->value,
                    'lead_age' => $activity->agingBadge($pipeline, $thresholds),
                    'stage_context' => $stage,
                    'current_owner' => $stage['owner_label'],
                ];
            });

        // ── Recent pipeline activity ──────────────────────────────────────────
        $recentPipelines = (clone $visiblePipelines)
            ->with(['assignedSaq:id,full_name', 'siteProfile.address'])
            ->latest('status_changed_at')
            ->limit(8)
            ->get()
            ->map(fn (Pipeline $pipeline): array => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'pipeline_id' => $pipeline->pipeline_id,
                'status' => $pipeline->status->value,
                'status_changed_at' => $pipeline->status_changed_at,
                'assigned_saq' => $pipeline->assignedSaq?->full_name,
                'lead_profile' => $pipeline->siteProfile?->name,
                'location' => collect([
                    $pipeline->siteProfile?->address?->city,
                    $pipeline->siteProfile?->address?->province,
                ])->filter()->implode(', '),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'in_acquisition' => $inAcquisition,
                'design_plan_for_approval' => $designPlanForApproval,
                'pending_approval' => $pendingApproval,
                'ready_for_service' => $readyForService,
                'active_projects' => $activeProjectCount,
            ],
            'activeProjects' => $activeProjects,
            'recentPipelines' => $recentPipelines,
            'statusDistribution' => $statusDistribution,
            'attentionItems' => $attentionItems,
            'agingOverview' => $agingOverview,
        ]);
    }
}
