<?php

namespace App\Models;

use App\Enums\ProviderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['site_survey_id', 'isp_name', 'contact_name', 'contact_email', 'status', 'feasible_at', 'notes'])]
class NetworkFeasibility extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ProviderStatus::class,
            'feasible_at' => 'datetime',
        ];
    }

    public function siteSurvey(): BelongsTo
    {
        return $this->belongsTo(SiteSurvey::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'network_feasibility_documents')
            ->withPivot('created_at');
    }

    /**
     * The JO checklist — seeded once the pipeline is approved and a Project exists
     * (NetworkFeasibilityTaskDefinitions, InitializeProject::carryForwardIsps).
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(NetworkFeasibilityTask::class)->orderBy('sort_order');
    }
}
