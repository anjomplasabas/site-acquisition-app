<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['site_profile_id', 'name', 'total_units', 'total_floors'])]
class Tower extends Model
{
    public function siteProfile(): BelongsTo
    {
        return $this->belongsTo(SiteProfile::class);
    }

    public function siteSurveys(): HasMany
    {
        return $this->hasMany(SiteSurvey::class);
    }

    public function sitePlanningAssignments(): HasMany
    {
        return $this->hasMany(SitePlanningAssignment::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
