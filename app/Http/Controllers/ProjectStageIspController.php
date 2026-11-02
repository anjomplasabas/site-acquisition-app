<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStageIsp;
use App\Support\ProjectStageFieldDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectStageIspController extends Controller
{
    public function store(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        $this->authorizeManage($project, $stage);

        $validated = $request->validate([
            'isp_name' => ['required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        // ISPs added directly here (rather than seeded from the Network module — see
        // InitializeProject::carryForwardIsps) have no network_feasibility_id and therefore no
        // JO checklist to action; ProjectStageIsp::allTasksDone() treats that as trivially done.
        $stage->isps()->create([
            'isp_name' => $validated['isp_name'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'ISP added.');
    }

    public function update(Request $request, Project $project, ProjectStage $stage, ProjectStageIsp $isp): RedirectResponse
    {
        $this->authorizeManage($project, $stage);
        $this->ensureIspBelongsToStage($stage, $isp);

        $validated = $request->validate([
            'isp_name' => ['required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $isp->update($validated);

        return back()->with('success', 'ISP updated.');
    }

    public function destroy(Project $project, ProjectStage $stage, ProjectStageIsp $isp): RedirectResponse
    {
        $this->authorizeManage($project, $stage);
        $this->ensureIspBelongsToStage($stage, $isp);

        $isp->delete();

        return back()->with('success', 'ISP removed.');
    }

    private function authorizeManage(Project $project, ProjectStage $stage): void
    {
        $this->authorize('manageStage', [$project, $stage->stage]);

        // ISP sections are Network-owned — Infra can see them but not change them.
        $ispField = collect(ProjectStageFieldDefinitions::forStage($stage->stage))->firstWhere('type', 'isp_task_list');
        abort_if(
            $ispField !== null && ! ProjectStageFieldDefinitions::isEditableBy(auth()->user(), $ispField),
            403,
            'Only the Network Department can modify ISP Status.'
        );

        abort_if($stage->project_id !== $project->id, 403);
        abort_if($project->current_stage !== $stage->stage, 422, 'This stage is not currently active.');
    }

    private function ensureIspBelongsToStage(ProjectStage $stage, ProjectStageIsp $isp): void
    {
        abort_if($isp->project_stage_id !== $stage->id, 403);
    }
}
