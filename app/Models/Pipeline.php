<?php

namespace App\Models;

use App\Enums\PipelineStatus;
use App\Enums\PriorityLevel;
use App\Support\LeadName;
use Database\Factories\PipelineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'normalized_name', 'pipeline_id', 'project_manager_id', 'assigned_saq_id', 'site_profile_id', 'endorsement_date', 'site_survey_id', 'priority_level', 'status', 'status_changed_at', 'last_activity_at', 'commercial_remarks'])]
class Pipeline extends Model
{
    /** @use HasFactory<PipelineFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PipelineStatus::class,
            'priority_level' => PriorityLevel::class,
            'endorsement_date' => 'datetime',
            'status_changed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Pipeline $pipeline) {
            $pipeline->status_changed_at = now();
            $pipeline->last_activity_at ??= now();
            $pipeline->normalized_name = LeadName::normalize($pipeline->name);
        });

        static::updating(function (Pipeline $pipeline) {
            if ($pipeline->isDirty('name')) {
                $pipeline->normalized_name = LeadName::normalize($pipeline->name);
            }

            if ($pipeline->isDirty('status')) {
                $pipeline->status_changed_at = now();
            }
        });
    }

    /**
     * The pipeline's default/untowered Project — unchanged behavior for the common case where a
     * site has no more than one tower. Once a site fans out into multiple towers, use projects()
     * for the full per-tower list instead.
     */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class)->whereNull('tower_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function networkSiteAssignment(): HasOne
    {
        return $this->hasOne(NetworkSiteAssignment::class);
    }

    /**
     * The pipeline's default/untowered Site Planning assignment — see project() above for the
     * same single-vs-multi-tower distinction.
     */
    public function sitePlanningAssignment(): HasOne
    {
        return $this->hasOne(SitePlanningAssignment::class)->whereNull('tower_id');
    }

    public function sitePlanningAssignments(): HasMany
    {
        return $this->hasMany(SitePlanningAssignment::class);
    }

    public function siteSurveys(): HasMany
    {
        return $this->hasMany(SiteSurvey::class, 'pipeline_id');
    }

    public function towers(): HasManyThrough
    {
        return $this->hasManyThrough(Tower::class, SiteProfile::class, 'id', 'site_profile_id', 'site_profile_id', 'id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function assignedSaq(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_saq_id');
    }

    public function siteProfile(): BelongsTo
    {
        return $this->belongsTo(SiteProfile::class);
    }

    public function siteSurvey(): BelongsTo
    {
        return $this->belongsTo(SiteSurvey::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'pipeline_documents')
            ->withPivot('created_at');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PipelineApproval::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PipelineStatusLog::class)->orderBy('created_at');
    }

    public function remarkLogs(): HasMany
    {
        return $this->hasMany(PipelineRemarkLog::class)->latest();
    }

    public function pipelineTouches(): HasMany
    {
        return $this->hasMany(PipelineTouch::class);
    }
}
