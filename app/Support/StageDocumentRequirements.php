<?php

namespace App\Support;

use App\Enums\PipelineStatus;
use App\Enums\ProjectStage as ProjectStageEnum;
use App\Models\Pipeline;
use App\Models\ProjectStage;
use App\Models\StageDocumentRequirement;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StageDocumentRequirements
{
    /**
     * Every active document category for a Pipeline stage, required or not — what a user is
     * allowed to pick from when uploading, since "not required" should still mean "uploadable",
     * not "invisible". Use requiredFor() instead when the question is "does this block
     * completion".
     *
     * @return Collection<int, StageDocumentRequirement>
     */
    public function activeFor(PipelineStatus|string $stage): Collection
    {
        $stageValue = $stage instanceof PipelineStatus ? $stage->value : $stage;

        return StageDocumentRequirement::query()
            ->where('module', 'pipeline')
            ->where('stage', $stageValue)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, StageDocumentRequirement> */
    public function requiredFor(PipelineStatus|string $stage): Collection
    {
        return $this->activeFor($stage)->where('is_required', true)->values();
    }

    /**
     * Every active document category for a Project stage, required or not — see activeFor()'s
     * docblock for why "not required" still means "uploadable". Use requiredForProjectStage()
     * instead when the question is "does this block completion".
     *
     * @return Collection<int, StageDocumentRequirement>
     */
    public function activeForProjectStage(ProjectStageEnum|string $stage): Collection
    {
        $stageValue = $stage instanceof ProjectStageEnum ? $stage->value : $stage;

        return StageDocumentRequirement::query()
            ->where('module', 'project')
            ->where('stage', $stageValue)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, StageDocumentRequirement> */
    public function requiredForProjectStage(ProjectStageEnum|string $stage): Collection
    {
        return $this->activeForProjectStage($stage)->where('is_required', true)->values();
    }

    /** @return Collection<int, string> */
    public function missingForProjectStage(ProjectStage $stage): Collection
    {
        $required = $this->requiredForProjectStage($stage->stage)->pluck('name');

        if ($required->isEmpty()) {
            return collect();
        }

        $attachedCategories = $stage->documents()->whereNotNull('category')->pluck('category');

        return $required->diff($attachedCategories)->values();
    }

    public function ensureProjectStageSatisfied(ProjectStage $stage): void
    {
        $missing = $this->missingForProjectStage($stage);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'required_documents' => 'Missing required documents: '.$missing->implode(', '),
            ]);
        }
    }

    /** @return Collection<int, string> */
    public function missingFor(Pipeline $pipeline, PipelineStatus|string|null $stage = null): Collection
    {
        $stageValue = $stage instanceof PipelineStatus
            ? $stage->value
            : ($stage ?? $pipeline->status->value);

        $required = $this->requiredFor($stageValue)->pluck('name');

        if ($required->isEmpty()) {
            return collect();
        }

        $pipelineCategories = $pipeline->documents()
            ->where(function ($query) use ($stageValue): void {
                $query->where('uploaded_status', $stageValue)
                    ->orWhereNull('uploaded_status');
            })
            ->whereNotNull('category')
            ->pluck('category');

        $surveyCategories = $pipeline->siteSurvey?->documents()
            ->where(function ($query) use ($stageValue): void {
                $query->where('uploaded_status', $stageValue)
                    ->orWhereNull('uploaded_status');
            })
            ->whereNotNull('category')
            ->pluck('category') ?? collect();

        return $required->diff($pipelineCategories->merge($surveyCategories)->unique())->values();
    }

    public function ensureSatisfied(Pipeline $pipeline, PipelineStatus|string|null $stage = null): void
    {
        $missing = $this->missingFor($pipeline, $stage);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'required_documents' => 'Missing required documents: '.$missing->implode(', '),
            ]);
        }
    }
}
