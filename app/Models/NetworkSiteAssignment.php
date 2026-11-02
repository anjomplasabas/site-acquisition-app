<?php

namespace App\Models;

use App\Enums\IspTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['pipeline_id', 'site_survey_id', 'assigned_at'])]
class NetworkSiteAssignment extends Model
{
    /** @var list<string> */
    protected $appends = ['jo_progress'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    /**
     * Aggregate JO checklist progress across every ISP on this site, distinct from — and much
     * later in the lifecycle than — feasibility completion (network_feasibility_completed_at):
     * feasibility is "have we checked which ISPs are viable" (Site Survey); this is "how far
     * along is the actual JO/installation work" (only exists once a Project has been created,
     * see InitializeProject::carryForwardIsps). Null until at least one task has been seeded.
     *
     * @return array{done: int, total: int}|null
     */
    public function getJoProgressAttribute(): ?array
    {
        $tasks = $this->siteSurvey?->networkFeasibilities->flatMap->tasks ?? collect();

        if ($tasks->isEmpty()) {
            return null;
        }

        return [
            'done' => $tasks->where('status', IspTaskStatus::Done)->count(),
            'total' => $tasks->count(),
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

    /**
     * Documents inherited from the pipeline at approval time (MOA/NTP — see
     * InitializeProject::carryForwardMoaNtpDocumentsToNetworkSite), mirroring how the same
     * files are shared onto the Project's Planning stage.
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'network_site_assignment_documents')
            ->withPivot('created_at');
    }

    /**
     * Find or create the assignment for a pipeline the moment it enters Site Survey.
     * Idempotent: re-entering Site Survey later (reopen, rejected approval) reuses the
     * same row instead of creating a duplicate, since it's meant to be an audit trail.
     */
    public static function ensureFor(Pipeline $pipeline, SiteSurvey $siteSurvey): self
    {
        return static::firstOrCreate(
            ['pipeline_id' => $pipeline->id],
            ['site_survey_id' => $siteSurvey->id, 'assigned_at' => now()],
        );
    }
}
