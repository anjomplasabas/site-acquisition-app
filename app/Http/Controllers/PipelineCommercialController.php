<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\PipelineStatus;
use App\Http\Requests\SetCommercialsRequest;
use App\Models\ApprovalConfig;
use App\Models\Document;
use App\Models\Pipeline;
use App\Models\PipelineApproval;
use App\Support\DocumentUploader;
use App\Support\PipelineNotifier;
use App\Support\StageDocumentRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PipelineCommercialController extends PipelineController
{
    public function saveCommercialsProgress(SetCommercialsRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('handleCommercials', $pipeline);
        abort_if($pipeline->status !== PipelineStatus::Commercials, 422, 'Pipeline is not in the correct status.');

        $validated = $request->validated();

        $pipeline->update(['commercial_remarks' => $validated['remarks'] ?? null]);
        $this->logRemark($pipeline, PipelineStatus::Commercials, $validated['remarks'] ?? null, 'commercial');

        if (! empty($validated['files'])) {
            foreach ($validated['files'] as $file) {
                $document = $this->documents->store($file, 'commercial-documents', auth()->user(), $validated['document_category'] ?? null, PipelineStatus::Commercials->value);
                $pipeline->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
            }
        }

        $this->recordActivity($pipeline, 'saved commercials progress');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Commercials progress saved.');
    }

    public function setCommercials(SetCommercialsRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('handleCommercials', $pipeline);
        abort_if($pipeline->status !== PipelineStatus::Commercials, 422, 'Pipeline is not in the correct status.');

        $activeConfigs = ApprovalConfig::where('is_active', true)
            ->orderBy('step')
            ->get();

        abort_if(
            $activeConfigs->isEmpty(),
            422,
            'No active approval configuration found. Please set up approval steps first.'
        );

        $validated = $request->validated();

        DB::transaction(function () use ($pipeline, $activeConfigs, $validated) {
            $pipeline->update([
                'commercial_remarks' => $validated['remarks'] ?? null,
                'status' => PipelineStatus::Approval,
            ]);

            $this->logStatus($pipeline, PipelineStatus::Approval);
            $this->logRemark($pipeline, PipelineStatus::Commercials, $validated['remarks'] ?? null, 'commercial');

            if (! empty($validated['files'])) {
                foreach ($validated['files'] as $file) {
                    $document = $this->documents->store($file, 'commercial-documents', auth()->user(), $validated['document_category'] ?? null, PipelineStatus::Commercials->value);
                    $pipeline->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
                }
            }

            $this->ensureRequiredDocumentsAreAttached($pipeline, PipelineStatus::Commercials);

            foreach ($activeConfigs as $config) {
                PipelineApproval::create([
                    'pipeline_id' => $pipeline->id,
                    'step' => $config->step,
                    'role_id' => $config->role_id,
                    'status' => ApprovalStatus::Pending,
                ]);
            }
        });

        $this->recordActivity($pipeline, 'submitted commercials');

        $firstApproval = $pipeline->approvals()->where('status', ApprovalStatus::Pending)->orderBy('step')->first();
        if ($firstApproval !== null) {
            app(PipelineNotifier::class)->approvalPending($pipeline, $firstApproval, auth()->user());
        }

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Commercials submitted. The approval process has been triggered.');
    }

    public function reviseCommercials(SetCommercialsRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('handleCommercials', $pipeline);
        abort_if($pipeline->status !== PipelineStatus::Approval, 422, 'Pipeline is not in the correct status.');

        $validated = $request->validated();

        $pipeline->update(['commercial_remarks' => $validated['remarks'] ?? null]);
        $this->logRemark($pipeline, PipelineStatus::Approval, $validated['remarks'] ?? null, 'commercial');

        if (! empty($validated['files'])) {
            foreach ($validated['files'] as $file) {
                $document = $this->documents->store($file, 'commercial-documents', auth()->user(), $validated['document_category'] ?? null, PipelineStatus::Approval->value);
                $pipeline->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
            }
        }

        $pipeline->approvals()->update([
            'status' => ApprovalStatus::Pending,
            'decided_at' => null,
            'remarks' => null,
        ]);
        $this->recordActivity($pipeline, 'revised commercials');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Commercials revised. All approval steps have been reset to pending.');
    }

    public function deleteCommercialDocument(Pipeline $pipeline, Document $document): RedirectResponse
    {
        abort_if(
            ! in_array($pipeline->status, [PipelineStatus::Commercials, PipelineStatus::Approval]),
            422,
            'Pipeline is not in the correct status.'
        );
        $this->authorize('handleCommercials', $pipeline);
        abort_unless($pipeline->documents()->where('document_id', $document->id)->exists(), 403);

        if ($pipeline->status === PipelineStatus::Approval && $document->category !== null) {
            $requiredCategories = app(StageDocumentRequirements::class)->activeFor(PipelineStatus::Commercials)->pluck('name');

            if ($requiredCategories->contains($document->category)) {
                throw ValidationException::withMessages([
                    'document' => "Cannot remove a required document ({$document->category}) while the pipeline is in Approval. Use Revise instead.",
                ]);
            }
        }

        $pipeline->documents()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }
        $this->recordActivity($pipeline, 'deleted a commercial document');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'File removed.');
    }

    public function replaceCommercialDocument(Request $request, Pipeline $pipeline, Document $document): RedirectResponse
    {
        abort_if(
            ! in_array($pipeline->status, [PipelineStatus::Commercials, PipelineStatus::Approval]),
            422,
            'Pipeline is not in the correct status.'
        );
        $this->authorize('handleCommercials', $pipeline);
        abort_unless($pipeline->documents()->where('document_id', $document->id)->exists(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'commercial-documents');
        $this->recordActivity($pipeline, 'replaced a commercial document');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Document replaced.');
    }
}
