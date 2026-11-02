<?php

use App\Enums\ProjectStage as ProjectStageEnum;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\SitePlanningAssignment;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Support\StageTaskDefinitions;
use App\Support\StageTaskSeeder;

function monitoringProjectTyped(string $assignmentType, string $seededMatrixType): array
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Matrix Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $pipeline = Pipeline::create([
        'name' => 'Matrix Pipeline',
        'pipeline_id' => nextPipelineId(),
        'site_profile_id' => $siteProfile->id,
    ]);
    $survey = SiteSurvey::create(['site_profile_id' => $siteProfile->id, 'pipeline_id' => $pipeline->id]);
    SitePlanningAssignment::create([
        'pipeline_id' => $pipeline->id,
        'site_survey_id' => $survey->id,
        'assigned_at' => now(),
        'design_plan_type' => $assignmentType,
    ]);
    $project = Project::create(['pipeline_id' => $pipeline->id, 'current_stage' => 'Monitoring']);
    $stage = ProjectStage::create(['project_id' => $project->id, 'stage' => 'Monitoring', 'status' => 'Pending', 'order' => 3]);
    StageTaskSeeder::seedMissingFor($stage, $seededMatrixType);

    return compact('project', 'stage');
}

it('rebuilds wrong-type Monitoring checklists with carry-over and skips matching ones', function () {
    ['stage' => $wrongStage] = monitoringProjectTyped('Residential', 'FAMILIA');
    ['stage' => $correctStage] = monitoringProjectTyped('FAMILIA', 'FAMILIA');

    // A shared MDF task's completion should survive the rebuild.
    $wrongStage->stageTasks()->where('name', 'UPS')->update(['status' => 'Done', 'status_changed_at' => now()]);
    $correctTaskIds = $correctStage->stageTasks()->pluck('id')->sort()->values()->all();

    $this->artisan('app:resync-monitoring-matrix')
        ->expectsOutputToContain('Resynced 1 Monitoring checklist(s)')
        ->assertSuccessful();

    $expected = StageTaskDefinitions::forField(ProjectStageEnum::Monitoring, 'infrastructure_deployment_status', 'Residential');
    $tasks = $wrongStage->fresh()->stageTasks()->orderBy('sort_order')->get();
    expect($tasks->pluck('name')->all())->toBe(array_column($expected, 'name'))
        ->and($tasks->firstWhere('name', 'UPS')->status->value)->toBe('Done');

    // The already-correct project is untouched.
    expect($correctStage->fresh()->stageTasks()->pluck('id')->sort()->values()->all())->toBe($correctTaskIds);

    // Second run finds nothing left to fix.
    $this->artisan('app:resync-monitoring-matrix')
        ->expectsOutputToContain('Resynced 0 Monitoring checklist(s)')
        ->assertSuccessful();
});

it('treats FIBER reusing the Residential matrix as a match, not a mismatch', function () {
    // No Annex 2.b exists for FIBER — StageTaskDefinitions maps it to Residential by design.
    ['stage' => $stage] = monitoringProjectTyped('FIBER', 'FIBER');
    $taskIds = $stage->stageTasks()->pluck('id')->sort()->values()->all();

    $this->artisan('app:resync-monitoring-matrix')
        ->expectsOutputToContain('Resynced 0 Monitoring checklist(s)')
        ->assertSuccessful();

    expect($stage->fresh()->stageTasks()->pluck('id')->sort()->values()->all())->toBe($taskIds);
});
