<?php

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'type', 'filename', 'file_path', 'uploaded_by', 'uploaded_status', 'category', 'version', 'root_document_id'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $appends = ['url'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * Every document is its own version root until DocumentUploader::replace() supersedes it —
     * this guarantees root_document_id is never null, regardless of which code path created the
     * row, so versions() never needs a fallback to $this->id.
     */
    protected static function booted(): void
    {
        static::created(function (self $document): void {
            if ($document->root_document_id === null) {
                $document->forceFill(['root_document_id' => $document->id])->saveQuietly();
            }
        });
    }

    /** Every version of this logical document (including itself), newest first. */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'root_document_id', 'root_document_id')->orderByDesc('version');
    }

    public function isCurrentVersion(): bool
    {
        return $this->superseded_at === null;
    }

    /** @return Attribute<string|null, never> */
    protected function url(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->hasStoredFile()) {
                return null;
            }

            $disk = (string) config('filesystems.documents_disk', 'public');
            $storage = Storage::disk($disk);

            if ($disk === 's3') {
                return $storage->temporaryUrl($this->file_path, now()->addMinutes(60));
            }

            return $storage->url($this->file_path);
        });
    }

    private function hasStoredFile(): bool
    {
        return filled($this->file_path) && $this->file_path !== '0';
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function siteSurveys(): BelongsToMany
    {
        return $this->belongsToMany(SiteSurvey::class, 'site_survey_documents')
            ->withPivot('created_at');
    }

    public function pipelines(): BelongsToMany
    {
        return $this->belongsToMany(Pipeline::class, 'pipeline_documents')
            ->withPivot('created_at');
    }

    public function projectStages(): BelongsToMany
    {
        return $this->belongsToMany(ProjectStage::class, 'project_stage_documents')
            ->withPivot('created_at', 'status');
    }

    public function ispTasks(): BelongsToMany
    {
        return $this->belongsToMany(IspTask::class, 'isp_task_documents')
            ->withPivot('created_at');
    }

    public function stageTasks(): BelongsToMany
    {
        return $this->belongsToMany(StageTask::class, 'stage_task_documents')
            ->withPivot('created_at');
    }

    public function networkFeasibilities(): BelongsToMany
    {
        return $this->belongsToMany(NetworkFeasibility::class, 'network_feasibility_documents')
            ->withPivot('created_at');
    }

    public function networkFeasibilityTasks(): BelongsToMany
    {
        return $this->belongsToMany(NetworkFeasibilityTask::class, 'network_feasibility_task_documents')
            ->withPivot('created_at');
    }

    public function networkSiteAssignments(): BelongsToMany
    {
        return $this->belongsToMany(NetworkSiteAssignment::class, 'network_site_assignment_documents')
            ->withPivot('created_at');
    }

    public function sitePlanningAssignments(): BelongsToMany
    {
        return $this->belongsToMany(SitePlanningAssignment::class, 'site_planning_assignment_documents')
            ->withPivot('created_at');
    }

    public function sitePlanningTasks(): BelongsToMany
    {
        return $this->belongsToMany(SitePlanningTask::class, 'site_planning_task_documents')
            ->withPivot('created_at');
    }

    public function siteSurveyTasks(): BelongsToMany
    {
        return $this->belongsToMany(SiteSurveyTask::class, 'site_survey_task_documents')
            ->withPivot('created_at');
    }

    public function designPlanAssignments(): BelongsToMany
    {
        return $this->belongsToMany(SitePlanningAssignment::class, 'design_plan_documents')
            ->withPivot('created_at');
    }

    /**
     * Whether no parent still references this document — the shared gate every document-delete
     * action across the app checks before removing the underlying file, since a single Document
     * row can be attached to several parents at once (e.g. a Commercials MOA shared onto a
     * Project's Planning stage and the Network Site).
     */
    public function isOrphaned(): bool
    {
        return ! $this->pipelines()->exists()
            && ! $this->siteSurveys()->exists()
            && ! $this->projectStages()->exists()
            && ! $this->ispTasks()->exists()
            && ! $this->stageTasks()->exists()
            && ! $this->networkFeasibilities()->exists()
            && ! $this->networkFeasibilityTasks()->exists()
            && ! $this->networkSiteAssignments()->exists()
            && ! $this->sitePlanningAssignments()->exists()
            && ! $this->sitePlanningTasks()->exists()
            && ! $this->siteSurveyTasks()->exists()
            && ! $this->designPlanAssignments()->exists();
    }
}
