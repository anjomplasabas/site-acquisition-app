<?php

namespace App\Models;

use App\Enums\IspTaskStatus;
use App\Enums\ProjectStage as ProjectStageEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable(['project_stage_id', 'source_project_stage_isp_id', 'network_feasibility_id', 'isp_name', 'notes'])]
class ProjectStageIsp extends Model
{
    /** @var list<string> */
    protected $appends = ['progress'];

    public function projectStage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class);
    }

    /**
     * The Planning-stage ISP this row was carried forward from (e.g. an Execution ISP seeded
     * from Planning on stage completion), if any. Null for ISPs added directly on their stage.
     */
    public function sourceProjectStageIsp(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_project_stage_isp_id');
    }

    /**
     * The Network Site ISP this row was seeded from (InitializeProject::carryForwardIsps), if
     * any. Null for ISPs added directly in Project Management — those have no JO checklist.
     */
    public function networkFeasibility(): BelongsTo
    {
        return $this->belongsTo(NetworkFeasibility::class);
    }

    /**
     * The linked Network Site ISP's JO checklist, as seen from this row's own stage. Monitoring
     * shows and can action the checklist in full, matching the Network module and Planning/
     * Execution exactly — not just the 2 tasks tagged for Monitoring specifically (that narrower
     * slice was the pre-existing behavior; the whole point of this method is now "does this stage
     * have any tasks to show," not "which subset belongs to it").
     */
    public function tasksForCurrentStage(): Collection
    {
        if ($this->networkFeasibility === null) {
            return collect();
        }

        if ($this->projectStage->stage === ProjectStageEnum::Monitoring) {
            return $this->networkFeasibility->tasks;
        }

        return $this->networkFeasibility->tasks->where('project_stage', $this->projectStage->stage);
    }

    /**
     * True when there's no JO checklist to gate on (an ISP added directly in Project Management,
     * not sourced from the Network module), or when every task in this stage's slice is Done.
     */
    public function allTasksDone(): bool
    {
        if ($this->networkFeasibility === null) {
            return true;
        }

        $tasks = $this->tasksForCurrentStage();

        return $tasks->isNotEmpty() && $tasks->every(fn (NetworkFeasibilityTask $task): bool => $task->status === IspTaskStatus::Done);
    }

    /** @return array{done: int, total: int} */
    public function getProgressAttribute(): array
    {
        $tasks = $this->tasksForCurrentStage();

        return [
            'done' => $tasks->where('status', IspTaskStatus::Done)->count(),
            'total' => $tasks->count(),
        ];
    }
}
