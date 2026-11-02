<?php

namespace App\Models;

use App\Enums\IspTaskStatus;
use App\Enums\ProjectStage as ProjectStageEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['project_id', 'stage', 'status', 'order', 'field_values', 'started_at', 'completed_at', 'completed_by_id'])]
class ProjectStage extends Model
{
    protected $appends = ['infrastructure_progress_percent', 'network_progress_percent', 'monitoring_progress_percent'];

    protected function casts(): array
    {
        return [
            'stage' => ProjectStageEnum::class,
            'field_values' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'project_stage_documents')
            ->withPivot('created_at', 'status');
    }

    /**
     * chaperone() auto-populates each child's inverse projectStage relation with zero extra
     * queries whenever isps is loaded through its parent — ProjectStageIsp::tasksForCurrentStage()
     * relies on that relation being set without a fresh lazy-load, since a freshly re-queried
     * ProjectStage instance would defeat Eloquent's circular-serialization guard (which tracks by
     * object identity) and risk unbounded recursion the moment a ProjectStage with loaded isps
     * gets serialized (e.g. rendered into a debug error page).
     */
    public function isps(): HasMany
    {
        return $this->hasMany(ProjectStageIsp::class)->chaperone();
    }

    public function stageTasks(): HasMany
    {
        return $this->hasMany(StageTask::class)->orderBy('sort_order');
    }

    /**
     * Weighted % of Monitoring's fixed Infrastructure checklist that is Done — an In-Progress
     * task earns no partial credit. Null (not 0) when stageTasks hasn't been seeded/loaded (it
     * isn't seeded until Planning completes and its design_plan_type is known — see
     * ProjectStageController::complete), so it can be excluded from monitoring_progress_percent's
     * average rather than dragging it down. Float, not int, since two of the three type matrices
     * (Annex 2.b.i/2.b.ii) use fractional weights down to 1.66/3.33/3.34.
     */
    public function getInfrastructureProgressPercentAttribute(): ?float
    {
        $tasks = $this->stageTasks->where('field_key', 'infrastructure_deployment_status');

        if ($tasks->isEmpty()) {
            return null;
        }

        return round((float) $tasks->where('status', IspTaskStatus::Done)->sum('weight'), 2);
    }

    /**
     * Done ratio across every Network Deployment Status ISP task — the full 9-task JO checklist
     * per ISP (ProjectStageIsp::tasksForCurrentStage(), matching the Network module's own view),
     * unweighted since these tasks carry no weight value.
     */
    public function getNetworkProgressPercentAttribute(): ?int
    {
        $tasks = $this->isps->flatMap(fn (ProjectStageIsp $isp): Collection => $isp->tasksForCurrentStage());

        if ($tasks->isEmpty()) {
            return null;
        }

        return (int) round(100 * $tasks->where('status', IspTaskStatus::Done)->count() / $tasks->count());
    }

    /**
     * The Monitoring stage's overall completion %, blending Infrastructure's weighted score
     * with Network's ISP-task done ratio in equal parts — excludes whichever side has no data
     * yet rather than treating it as 0%.
     */
    public function getMonitoringProgressPercentAttribute(): ?float
    {
        $parts = array_filter(
            [$this->infrastructure_progress_percent, $this->network_progress_percent],
            fn (?float $value): bool => $value !== null
        );

        return $parts === [] ? null : round(array_sum($parts) / count($parts), 2);
    }

    /**
     * Derives Monitoring's Project Status from the Infrastructure Deployment Status checklist
     * only (not Network) — null while every task is still Pending (leaves project_status
     * untouched), 'On-going' once any task has started, 'Done' once every task is Done. Callers
     * are responsible for not overwriting a manually-set 'On-hold' value with this result.
     */
    public function inferredProjectStatusFromInfrastructure(): ?string
    {
        $tasks = $this->stageTasks->where('field_key', 'infrastructure_deployment_status');

        if ($tasks->isEmpty() || $tasks->every(fn (StageTask $task): bool => $task->status === IspTaskStatus::Pending)) {
            return null;
        }

        return $tasks->every(fn (StageTask $task): bool => $task->status === IspTaskStatus::Done) ? 'Done' : 'On-going';
    }

    /**
     * Writes inferredProjectStatusFromInfrastructure() into field_values.project_status, unless
     * the PM has manually set 'On-hold' (a manual override until they pick something else).
     * Shared by StageTaskController (task status changes) and the design-plan-type
     * reclassification flow when checklists are rebuilt.
     */
    public function syncProjectStatusFromInfrastructure(): void
    {
        $this->refresh()->load('stageTasks');
        $inferred = $this->inferredProjectStatusFromInfrastructure();
        $currentStatus = $this->field_values['project_status'] ?? null;

        if ($inferred !== null && $currentStatus !== 'On-hold') {
            $this->update(['field_values' => array_merge($this->field_values ?? [], ['project_status' => $inferred])]);
        }
    }
}
