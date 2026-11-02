<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['site_survey_id', 'document_id'])]
#[Table(timestamps: false)]
class SiteSurveyDocument extends Model
{
    public function siteSurvey(): BelongsTo
    {
        return $this->belongsTo(SiteSurvey::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
