<?php

namespace App\Models;

use Database\Factories\SiteSurveyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'site_profile_id', 'pipeline_id', 'tower_id', 'surveyor_id', 'survey_date',
    'survey_completed_at', 'survey_completed_by_id',
    'network_feasibility_completed_at', 'network_feasibility_completed_by_id',
    'floors_surveyed', 'actual_unit_count', 'has_roof_access', 'equipment_notes', 'remarks',
    'building_network_system', 'indoor_outlet_capacity', 'isp_count_per_unit',
    'telco_room_location', 'electrical_room_location',
    'lobby_booth_allowed', 'lobby_standee_allowed', 'lobby_flyers_stand_allowed',
    'roaming_sales_rep_allowed', 'telco_room_fee',
    'marketing_paraphernalia', 'brochure_posting_location',
])]
class SiteSurvey extends Model
{
    /** @use HasFactory<SiteSurveyFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'survey_date' => 'datetime',
            'survey_completed_at' => 'datetime',
            'network_feasibility_completed_at' => 'datetime',
            'has_roof_access' => 'boolean',
            'lobby_booth_allowed' => 'boolean',
            'lobby_standee_allowed' => 'boolean',
            'lobby_flyers_stand_allowed' => 'boolean',
            'roaming_sales_rep_allowed' => 'boolean',
        ];
    }

    public function siteProfile(): BelongsTo
    {
        return $this->belongsTo(SiteProfile::class);
    }

    public function tower(): BelongsTo
    {
        return $this->belongsTo(Tower::class);
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function surveyCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'survey_completed_by_id');
    }

    public function networkFeasibilityCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'network_feasibility_completed_by_id');
    }

    public function providers(): HasMany
    {
        return $this->hasMany(SurveyProvider::class);
    }

    public function networkFeasibilities(): HasMany
    {
        return $this->hasMany(NetworkFeasibility::class);
    }

    public function siteSurveyTasks(): HasMany
    {
        return $this->hasMany(SiteSurveyTask::class)->orderBy('sort_order');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'site_survey_documents')
            ->withPivot('created_at');
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }

    /**
     * The pipeline attempt that created this survey row (site_surveys.pipeline_id) — distinct
     * from pipelines() above, which is the inverse of a Pipeline's own site_survey_id pointer.
     * Used to look up every tower survey a pipeline created once it has more than one.
     */
    public function owningPipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }
}
