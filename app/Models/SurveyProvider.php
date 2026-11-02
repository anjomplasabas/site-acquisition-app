<?php

namespace App\Models;

use App\Enums\ProviderStatus;
use Database\Factories\SurveyProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['site_survey_id', 'provider_name', 'status', 'notes'])]
#[Table(timestamps: false)]
class SurveyProvider extends Model
{
    /** @use HasFactory<SurveyProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ProviderStatus::class,
        ];
    }

    public function siteSurvey(): BelongsTo
    {
        return $this->belongsTo(SiteSurvey::class);
    }
}
