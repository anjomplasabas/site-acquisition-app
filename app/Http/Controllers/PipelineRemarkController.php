<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddRemarkRequest;
use App\Models\Pipeline;
use App\Models\PipelineRemarkLog;
use Illuminate\Http\RedirectResponse;

class PipelineRemarkController extends PipelineController
{
    public function addRemark(Pipeline $pipeline, AddRemarkRequest $request): RedirectResponse
    {
        $this->authorize('view', $pipeline);

        PipelineRemarkLog::create([
            'pipeline_id' => $pipeline->id,
            'status' => $pipeline->status->value,
            'type' => 'visit',
            'content' => $request->validated('content'),
            'added_by' => auth()->id(),
        ]);
        $this->recordActivity($pipeline, 'added a remark');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Remark added.');
    }
}
