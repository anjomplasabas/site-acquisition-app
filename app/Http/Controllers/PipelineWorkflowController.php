<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Http\Requests\AssignSaqSpecialistRequest;
use App\Http\Requests\StartAcquisitionRequest;
use App\Models\Pipeline;
use App\Models\SitePlanningAssignment;
use App\Models\User;
use App\Support\PipelineNotifier;
use App\Support\SiteSurveyProgression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PipelineWorkflowController extends PipelineController
{
    public function assignSaq(AssignSaqSpecialistRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('assignSaq', $pipeline);

        // Normal flow stays Prospect/Prospecting-only (the policy enforces this for every
        // role), but the Super Admin may reassign at any stage — the resigned-SAQ case,
        // with mandatory remarks enforced by AssignSaqSpecialistRequest.
        $lateReassignment = ! in_array($pipeline->status, [PipelineStatus::Prospect, PipelineStatus::Prospecting], true);
        abort_if(
            $lateReassignment && auth()->user()->role?->name !== 'SUPER_ADMIN',
            422,
            'SAQ Specialist can only be assigned during Prospect or Prospecting.'
        );

        $validated = $request->validated();

        abort_unless(
            User::whereKey($validated['assigned_saq_id'])
                ->whereHas('role.permissions', fn ($query) => $query->where('pipeline_status', PipelineStatus::Prospecting->value))
                ->exists(),
            422,
            'The selected user must have Prospecting stage permission.'
        );

        if ($pipeline->status === PipelineStatus::Prospect) {
            $this->ensureRequiredDocumentsAreAttached($pipeline, PipelineStatus::Prospect);
        }

        DB::transaction(function () use ($pipeline, $validated): void {
            $pipeline->update(['assigned_saq_id' => $validated['assigned_saq_id']]);

            if ($pipeline->status === PipelineStatus::Prospect) {
                $pipeline->update(['status' => PipelineStatus::Prospecting]);
                $this->logStatus($pipeline, PipelineStatus::Prospecting);
                $this->logRemark($pipeline, PipelineStatus::Prospecting, 'SAQ Specialist assigned; prospecting started.');
            }
        });

        if ($lateReassignment) {
            $this->logRemark($pipeline, $pipeline->status, $validated['remarks']);
            $this->recordActivity($pipeline, 'reassigned the SAQ specialist');
        } else {
            $this->recordActivity($pipeline, 'assigned an SAQ specialist');
        }

        app(PipelineNotifier::class)->saqAssigned($pipeline, User::findOrFail($validated['assigned_saq_id']));

        return redirect()->back()->with('success', 'SAQ Specialist assigned.');
    }

    public function moveToSiteSurveyPending(Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('moveToSiteSurveyPending', $pipeline);
        abort_if($pipeline->status !== PipelineStatus::Prospecting, 422, 'Pipeline is not in the correct status.');

        $survey = SiteSurveyProgression::ensureSiteSurvey($pipeline);
        $this->ensureRequiredDocumentsAreAttached($pipeline, PipelineStatus::Prospecting);
        $pipeline->update(['status' => PipelineStatus::SiteSurveyPending]);
        $this->logStatus($pipeline, PipelineStatus::SiteSurveyPending);
        $assignment = SitePlanningAssignment::ensureFor($pipeline, $survey);
        $this->recordActivity($pipeline, 'moved the site to Site Survey Pending');
        app(PipelineNotifier::class)->siteSurveyPending($assignment, auth()->user());

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Pipeline moved to Site Survey Pending.');
    }

    public function startAcquisition(StartAcquisitionRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('startAcquisition', $pipeline);
        abort_if($pipeline->status !== PipelineStatus::Prospect, 422, 'Pipeline is not in Prospect status.');

        $validated = $request->validated();

        if (! empty($validated['files'])) {
            foreach ($validated['files'] as $file) {
                $document = $this->documents->store($file, 'pipeline-documents', auth()->user(), $validated['document_category'] ?? null, PipelineStatus::Prospect->value);
                $pipeline->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
            }
        }

        $this->ensureRequiredDocumentsAreAttached($pipeline, PipelineStatus::Prospect);

        $pipeline->update(['status' => PipelineStatus::Prospecting]);
        $this->logStatus($pipeline, PipelineStatus::Prospecting);
        $this->logRemark($pipeline, PipelineStatus::Prospecting, $validated['content'], 'visit');

        $this->recordActivity($pipeline, 'started prospecting');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Prospecting started.');
    }

    public function markActive(Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('markActive', $pipeline);
        abort_if($pipeline->status !== PipelineStatus::ReadyForDeployment, 422, 'Pipeline must be RFD to mark as RFS.');

        $pipeline->update(['status' => PipelineStatus::ReadyForService]);
        $this->logStatus($pipeline, PipelineStatus::ReadyForService);
        $this->recordActivity($pipeline, 'marked the site as ready for service');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Site marked as completed.');
    }

    public function markInactive(Pipeline $pipeline): RedirectResponse
    {
        abort_if(
            in_array($pipeline->status, [PipelineStatus::Inactive, PipelineStatus::Completed]),
            422,
            'Pipeline cannot be marked inactive from its current status.'
        );

        abort_if(
            $pipeline->status === PipelineStatus::Approval,
            422,
            'Pipeline is mid-approval and cannot be marked inactive. Reject or revise the commercials submission first.'
        );

        $this->authorize('markInactive', $pipeline);

        $pipeline->update(['status' => PipelineStatus::Inactive]);
        $this->logStatus($pipeline, PipelineStatus::Inactive);
        $this->recordActivity($pipeline, 'marked the site inactive');

        return $this->redirectToVisiblePipelineDestination($pipeline, 'Pipeline marked as Inactive.');
    }

    public function markCompleted(Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('markCompleted', $pipeline);
        abort_if($pipeline->status !== PipelineStatus::ReadyForService, 422, 'Pipeline must be RFS to mark as Completed.');

        $pipeline->update(['status' => PipelineStatus::Completed]);
        $this->logStatus($pipeline, PipelineStatus::Completed);
        $this->recordActivity($pipeline, 'marked the site completed');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Site marked as Completed.');
    }
}
