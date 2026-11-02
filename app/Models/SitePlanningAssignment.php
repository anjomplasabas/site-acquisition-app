<?php

namespace App\Models;

use App\Enums\IspTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'pipeline_id', 'tower_id', 'site_survey_id', 'assigned_at', 'design_plan_type', 'design_plan_assignee_id',
    'design_plan_completed_at', 'design_plan_completed_by_id',
    'design_plan_approved_at', 'design_plan_approved_by_id', 'design_plan_remarks', 'design_plan_revision_remarks',
])]
class SitePlanningAssignment extends Model
{
    /** @var list<string> */
    protected $appends = ['plans_drawing_progress_percent'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'design_plan_completed_at' => 'datetime',
            'design_plan_approved_at' => 'datetime',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function siteSurvey(): BelongsTo
    {
        return $this->belongsTo(SiteSurvey::class);
    }

    public function tower(): BelongsTo
    {
        return $this->belongsTo(Tower::class);
    }

    /** The Infra Designer assigned to work this site's Plans/Drawing checklist, if any. */
    public function designPlanAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'design_plan_assignee_id');
    }

    /** The Infra Designer (or whoever) who clicked "Complete Design Plan". */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'design_plan_completed_by_id');
    }

    /** The Infrastructure Manager who approved the completed design plan. */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'design_plan_approved_by_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'site_planning_assignment_documents')
            ->withPivot('created_at');
    }

    public function sitePlanningTasks(): HasMany
    {
        return $this->hasMany(SitePlanningTask::class)->orderBy('sort_order');
    }

    /**
     * A separate Photos & Documents collection at the end of the Design Plan stage —
     * deliberately distinct from documents() (Site Survey stage requirements) and from each
     * SitePlanningTask's own attachments, so uploads here don't get counted against either.
     */
    public function designPlanDocuments(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'design_plan_documents')
            ->withPivot('created_at');
    }

    /**
     * @return float|null Percent of the Plans/Drawing checklist's weight that's Done — null
     *                    until the checklist has been seeded (see SitePlanningTaskSeeder), mirrors
     *                    ProjectStage::getPlansDrawingProgressPercentAttribute()'s identical weighted-sum logic.
     */
    public function getPlansDrawingProgressPercentAttribute(): ?float
    {
        if ($this->sitePlanningTasks->isEmpty()) {
            return null;
        }

        return round((float) $this->sitePlanningTasks->where('status', IspTaskStatus::Done)->sum('weight'), 2);
    }

    /**
     * Find or create the assignment the moment a pipeline needs Infrastructure attention (see
     * PipelineController::moveToSiteSurveyPending) — before a surveyor is even assigned, so the
     * site appears in the Site Planning queue immediately. Idempotent: re-entering Site Survey
     * later (rejected approval, reopened survey) reuses the same row, since it persists as a
     * permanent record even after the pipeline moves on to Commercials/Approval/Project.
     */
    public static function ensureFor(Pipeline $pipeline, SiteSurvey $siteSurvey): self
    {
        return static::firstOrCreate(
            ['pipeline_id' => $pipeline->id, 'tower_id' => $siteSurvey->tower_id],
            ['site_survey_id' => $siteSurvey->id, 'assigned_at' => now()],
        );
    }
}
