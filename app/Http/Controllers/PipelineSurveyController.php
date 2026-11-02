<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Http\Requests\CompleteSurveyRequest;
use App\Models\Document;
use App\Models\Pipeline;
use App\Support\DocumentUploader;
use App\Support\SiteSurveyProgression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PipelineSurveyController extends PipelineController
{
    public function reopenSurvey(Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('handleCommercials', $pipeline);
        abort_if($pipeline->status !== PipelineStatus::Commercials, 422, 'Pipeline is not in the correct status.');

        SiteSurveyProgression::enterSiteSurvey($pipeline, auth()->user());
        $this->recordActivity($pipeline, 'reopened the survey');

        return $this->redirectToVisiblePipelineDestination($pipeline, 'Survey re-opened for editing.');
    }

    public function saveSurveyFindings(CompleteSurveyRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('editSurveyFindings', $pipeline);

        $this->persistSurveyData($request, $pipeline);
        $this->recordActivity($pipeline, 'saved survey findings');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Survey findings saved.');
    }

    public function saveProspecting(CompleteSurveyRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('update', $pipeline);
        abort_unless(
            in_array($pipeline->status, [PipelineStatus::Prospect, PipelineStatus::Prospecting], true),
            422,
            'Pipeline is not in the correct status.'
        );

        SiteSurveyProgression::ensureSiteSurvey($pipeline);
        $this->persistSurveyData($request, $pipeline, 'prospecting');
        $this->recordActivity($pipeline, 'saved prospecting details');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Prospecting details saved.');
    }

    public function deleteSurveyDocument(Pipeline $pipeline, Document $document): RedirectResponse
    {
        $this->authorize('deleteSurveyDocument', $pipeline);
        abort_if(
            $pipeline->status !== PipelineStatus::Prospecting,
            422,
            'Pipeline is not in the correct status.'
        );
        abort_unless($pipeline->siteSurvey?->documents()->where('document_id', $document->id)->exists(), 403);
        abort_if(
            $document->uploaded_status !== null && $document->uploaded_status !== $pipeline->status->value,
            403,
            'You do not have permission to delete this document.'
        );

        $pipeline->siteSurvey->documents()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }
        $this->recordActivity($pipeline, 'deleted a survey document');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'File removed.');
    }

    public function replaceSurveyDocument(Request $request, Pipeline $pipeline, Document $document): RedirectResponse
    {
        $this->authorize('deleteSurveyDocument', $pipeline);
        abort_if(
            $pipeline->status !== PipelineStatus::Prospecting,
            422,
            'Pipeline is not in the correct status.'
        );
        abort_unless($pipeline->siteSurvey?->documents()->where('document_id', $document->id)->exists(), 403);
        abort_if(
            $document->uploaded_status !== null && $document->uploaded_status !== $pipeline->status->value,
            403,
            'You do not have permission to replace this document.'
        );

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'survey-documents');
        $this->recordActivity($pipeline, 'replaced a survey document');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Document replaced.');
    }
}
