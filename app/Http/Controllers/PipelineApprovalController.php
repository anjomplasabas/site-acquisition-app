<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\PipelineStatus;
use App\Events\PipelineApproved;
use App\Http\Requests\DecideApprovalRequest;
use App\Models\Pipeline;
use App\Models\PipelineApproval;
use App\Support\PipelineNotifier;
use App\Support\SiteSurveyProgression;
use Illuminate\Http\RedirectResponse;

class PipelineApprovalController extends PipelineController
{
    public function decideApproval(DecideApprovalRequest $request, Pipeline $pipeline, PipelineApproval $pipelineApproval): RedirectResponse
    {
        $this->authorize('decideApproval', [$pipeline, $pipelineApproval]);
        abort_if($pipeline->status !== PipelineStatus::Approval, 422, 'Pipeline is not in the correct status.');
        abort_if($pipelineApproval->pipeline_id !== $pipeline->id, 403);
        abort_if($pipelineApproval->status !== ApprovalStatus::Pending, 422, 'This approval step has already been decided.');

        $validated = $request->validated();

        $pipelineApproval->update([
            'status' => ApprovalStatus::from($validated['decision']),
            'decided_at' => now(),
            'decided_by_id' => auth()->id(),
            'remarks' => $validated['remarks'] ?? null,
        ]);

        if ($validated['decision'] === 'rejected') {
            $pipeline->approvals()->delete();
            SiteSurveyProgression::enterSiteSurvey($pipeline, auth()->user());
            $this->logRemark($pipeline, PipelineStatus::Approval, $validated['remarks'] ?? null, 'approval');
            $this->recordActivity($pipeline, 'rejected an approval step');
            app(PipelineNotifier::class)->approvalRejected($pipeline, auth()->user(), $validated['remarks'] ?? null);

            return $this->redirectToVisiblePipelineDestination($pipeline, 'Approval rejected. Pipeline has been returned to Site Survey.');
        }

        $this->logRemark($pipeline, PipelineStatus::Approval, $validated['remarks'] ?? null, 'approval');

        $nextPending = $pipeline->approvals()
            ->where('status', ApprovalStatus::Pending)
            ->orderBy('step')
            ->first();

        if ($nextPending === null) {
            $pipeline->update(['status' => PipelineStatus::ReadyForDeployment]);
            $this->logStatus($pipeline, PipelineStatus::ReadyForDeployment);
            $this->recordActivity($pipeline, 'approved the final approval step');

            PipelineApproved::dispatch($pipeline);

            return $this->redirectToVisiblePipelineDestination($pipeline, 'All approvals completed. Project management has been initiated.');
        }

        $this->recordActivity($pipeline, 'approved an approval step');
        app(PipelineNotifier::class)->approvalPending($pipeline, $nextPending, auth()->user());

        return $this->redirectToVisiblePipelineDestination($pipeline, 'Step approved. Awaiting the next approval step.');
    }
}
