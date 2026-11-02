<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['site_survey_id', 'name', 'sort_order', 'notes'])]
class SiteSurveyTask extends Model
{
    public function siteSurvey(): BelongsTo
    {
        return $this->belongsTo(SiteSurvey::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'site_survey_task_documents')
            ->withPivot('created_at');
    }
}
